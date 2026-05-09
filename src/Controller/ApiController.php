<?php

declare(strict_types=1);

namespace Respatch\RespatchBundle\Controller;

use App\Entity\ProcessedMessage;
use DateTimeInterface;
use Respatch\RespatchBundle\Attribute\ResponseSchema;
use Respatch\RespatchBundle\Helper\ApiHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Zenstruck\Bytes;
use Zenstruck\Messenger\Monitor\History\Period;
use Zenstruck\Messenger\Monitor\History\Specification;
use Zenstruck\Messenger\Monitor\Stamp\TagStamp;
use Zenstruck\Messenger\Monitor\Transport\QueuedMessage;
use Zenstruck\Messenger\Monitor\Transport\TransportInfo;
use Zenstruck\Messenger\Monitor\Worker\WorkerInfo;

class ApiController extends AbstractController {

	const int MAX_LIMIT_PER_PAGE = 50;

	#[ResponseSchema(schema: [
		'type'       => 'object',
		'required'   => ['status'],
		'properties' => [
			'status' => ['type' => 'string'],
		],
	])]
	public function status(): JsonResponse {
		return $this->json([
			'status' => 'OK',
		]);
	}

	#[ResponseSchema(schema: [
		'type'       => 'object',
		'required'   => ['snapshot', 'messages'],
		'properties' => [
			'snapshot' => [
				'type'       => 'object',
				'required'   => ['successCount', 'failureCount', 'averageWaitTime', 'averageHandlingTime', 'totalSeconds'],
				'properties' => [
					'specification'       => ['type' => 'array'],
					'successCount'        => ['type' => 'integer'],
					'failureCount'        => ['type' => 'integer'],
					'averageWaitTime'     => ['type' => 'integer'],
					'averageHandlingTime' => ['type' => 'integer'],
					'totalSeconds'        => ['type' => 'integer'],
				],
			],
			'messages' => [
				'type'  => 'array',
				'items' => [
					'type'       => 'object',
					'required'   => [
						'id',
						'runId',
						'attempt',
						'type',
						'dispatchedAt',
						'receivedAt',
						'finishedAt',
						'transport',
						'tags',
						'results',
						'failure',
						'memoryUsage'
					],
					'properties' => [
						'id'           => ['type' => ['integer', 'string', 'null']],
						'runId'        => ['type' => 'integer'],
						'attempt'      => ['type' => 'integer'],
						'type'         => [
							'type'       => 'object',
							'required'   => ['class'],
							'properties' => [
								'class'       => ['type' => 'string'],
								'object'      => ['type' => ['object', 'null']],
								'description' => ['type' => ['string', 'null']],
							],
						],
						'description'  => ['type' => ['string', 'null']],
						'dispatchedAt' => ['type' => 'string'],
						'receivedAt'   => ['type' => 'string'],
						'finishedAt'   => ['type' => 'string'],
						'transport'    => ['type' => 'string'],
						'tags'         => ['type' => 'array', 'items' => ['type' => 'string']],
						'results'      => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'required'   => ['handler'],
								'properties' => [
									'data'    => ['type' => 'array'],
									'handler' => ['type' => 'string'],
								],
							],
						],
						'failure'      => ['type' => 'boolean'],
						'memoryUsage'  => ['type' => 'integer'],
					],
				],
			],
		],
	])]
	public function dashboard(ApiHelper $helper): JsonResponse {
		return $this->json([
			'snapshot' => Specification::create(Period::IN_LAST_DAY)->snapshot($helper->storage()),
			'messages' => Specification::new()->snapshot($helper->storage())->messages(),
		]);
	}


	public function recentMessages(Request $request, ApiHelper $helper): JsonResponse {
		$messages = Specification::new()->snapshot($helper->storage())->messages()->take(25);
		$result   = $messages->map(fn(ProcessedMessage $message) => [
			"id"        => $message->id(),
			"title"     => $message->type()->class(),
			"status"    => $message->failure()?->description(),
			"transport" => $message->transport(),
			"duration"  => $message->timeToProcess(),
			"memory"    => $message->memoryUsage()->format(),
			"handledAt" => $message->finishedAt()->format(DateTimeInterface::ATOM),
		]);

		return $this->json($result);
	}

	#[ResponseSchema(schema: [
		'type'       => 'object',
		'required'   => ['periods', 'period', 'metrics'],
		'properties' => [
			'periods' => ['type' => 'array'],
			'period'  => ['type' => ['string', 'null', 'object']],
			'metrics' => ['type' => 'array'],
		],
	])]
	public function statistics(Request $request, ApiHelper $helper): JsonResponse {
		$period        = Period::parse($request->query->getString('period'));
		$specification = Specification::create([
			'period' => $period,
		]);

		return $this->json([
			'periods' => [...Period::inLastCases(), ...Period::absoluteCases()],
			'period'  => $period,
			'metrics' => $specification->snapshot($helper->storage())->perMessageTypeMetrics(),
		]);
	}

	#[ResponseSchema(schema: [
		'type'       => 'object',
		'required'   => ['periods', 'period', 'snapshot', 'filters'],
		'properties' => [
			'periods'  => ['type' => 'array'],
			'period'   => ['type' => ['string', 'null', 'object']],
			'snapshot' => [
				'type'       => 'object',
				'required'   => ['successCount', 'failureCount', 'averageWaitTime', 'averageHandlingTime'],
				'properties' => [
					'successCount'        => ['type' => 'integer'],
					'failureCount'        => ['type' => 'integer'],
					'averageWaitTime'     => ['type' => 'integer'],
					'averageHandlingTime' => ['type' => 'integer'],
				],
			],
			'filters'  => ['type' => ['object', 'array']],
		],
	])]
	public function history(Request $request, ApiHelper $helper): JsonResponse {
		$tags    = [$request->query->get('tag')];
		$notTags = [];
		$period  = Period::parse($request->query->getString('period'));

		match ($schedule = $request->query->get('schedule')) {
			'_exclude' => $notTags[] = 'schedule',
			'_include' => null,
			default => $tags[] = $schedule,
		};

		$specification = Specification::create([
			'period'       => $period,
			'transport'    => $request->query->get('transport'),
			'status'       => $request->query->get('status'),
			'tags'         => \array_filter($tags),
			'not_tags'     => $notTags,
			'message_type' => $request->query->get('type'),
		]);

		return $this->json([
			'periods'  => [...Period::inLastCases(), ...Period::absoluteCases()],
			'period'   => $period,
			'snapshot' => $specification->snapshot($helper->storage()),
			'filters'  => $specification->filters($helper->storage()),
		]);
	}

	#[ResponseSchema(schema: [
		'type'       => 'object',
		'required'   => ['message', 'other_attempts'],
		'properties' => [
			'message'        => ['type' => ['object', 'null']],
			'other_attempts' => ['type' => 'array'],
		],
	])]
	public function detail(string $id, ApiHelper $helper): JsonResponse {
		if (!$message = $helper->storage()->find($id)) {
			throw $this->createNotFoundException('Message not found.');
		}

		return $this->json([
			'message'        => $message,
			'other_attempts' => $helper->storage()->filter(Specification::create(['run_id' => $message->runId()])),
		]);
	}

	public function transports(ApiHelper $helper): JsonResponse {
		$countable = $helper->transports->filter()->countable();

		if (!\count($countable)) {
			throw new \LogicException('No countable transports configured.');
		}

		$transports = array_map(fn(TransportInfo $info) => [
			"failure"     => $info->isFailure(),
			"name"        => $info->name(),
			"count"       => $info->isCountable() ? $info->count() : null,
			"workers"     => $info->isFailure() ? "n/a" : count($info->workers()),
			"usedWorkers" => $info->isFailure() ? "n/a" : array_sum(array_map(fn(WorkerInfo $worker) => $worker->isProcessing() ? 1 : 0, $info->workers())),
			"memory"      => new Bytes(array_sum(array_map(fn(WorkerInfo $worker) => $worker->memoryUsage()->value(), $info->workers())))->format(),
		], $helper->transports->filter()->excludeSync()->all());

		return $this->json($transports);
	}

	public function transport(Request $request, ApiHelper $helper, string $name): JsonResponse {
		$countable = $helper->transports->filter()->countable();

		$limit = $request->query->getInt('limit', self::MAX_LIMIT_PER_PAGE);

		if ($limit > self::MAX_LIMIT_PER_PAGE) {
			$limit = self::MAX_LIMIT_PER_PAGE;
		}

		if (!\count($countable)) {
			throw new \LogicException('No countable transports configured.');
		}

		if (!$name) {
			$name = $countable->names()[0];
		}

		$transport = $helper->transports->get($name);

		$messages = $transport->list($limit)->getIterator()
				|> iterator_to_array(...)
				|> (fn($x) => array_map(fn(QueuedMessage $message) => [
					"id"          => $message->id(),
					"title"       => $message->message()->shortName(),
					"transport"   => $name,
					"dispatched"  => $message->dispatchedAt()->format(DateTimeInterface::ATOM),
					"deleteToken" => $helper->generateCsrfToken('remove', $name,(string) $message->id()),
					"retryToken"  => $helper->generateCsrfToken('retry', $name, (string)$message->id()),
					"exception"   => $message->exception() ? [
						"class"       => $message->exception()->shortName(),
						"description" => $message->exception()->description(),
						"name"        => $message->exception()->class()
					] : null,
				], $x));

		return $this->json($messages);
	}


	public function removeTransportMessage(Request $request, ApiHelper $helper, string $name, string $id): JsonResponse {


		$helper->validateCsrfToken($request->query->getString('_token'), 'remove', $name, $id);

		$transport = $helper->transports->get($name);
		$message   = $transport->find($id) ?? throw $this->createNotFoundException('Message not found.');

		$transport->get()->reject($message->envelope());

		return new JsonResponse(null, 204);
	}


	public function retryFailedMessage(Request $request, ApiHelper $helper, MessageBusInterface $bus, string $name, string $id): JsonResponse {
		$helper->validateCsrfToken($request->request->getString('_token'), 'remove', $name, $id);
		$transport             = $helper->transports->get($name);
		$message               = $transport->find($id) ?? throw $this->createNotFoundException('Message not found.');
		$originalTransportName = $message->envelope()->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName() ?? throw $this->createNotFoundException('Original transport not found.');

		$bus->dispatch($message->envelope(), [
			new TagStamp('retry'),
			new TagStamp('manual'),
		]);
		$transport->get()->reject($message->envelope());

		return $this->json([
			'success' => true,
			'message' => \sprintf('Retrying message "%s" on transport "%s".', $message->message()->shortName(), $originalTransportName),
		]);
	}

	#[ResponseSchema(schema: [
		'type'       => 'object',
		'required'   => ['schedules', 'transports'],
		'properties' => [
			'schedules'  => ['type' => 'array'],
			'schedule'   => ['type' => ['object', 'null']],
			'transports' => ['type' => 'array'],
		],
	])]
	public function schedules(ApiHelper $helper, ?string $name = null): JsonResponse {
		if (!$helper->schedules) {
			throw new \LogicException('Scheduler must be configured to use the dashboard.');
		}

		if (!\count($helper->schedules)) {
			throw new \LogicException('No schedules configured.');
		}

		return $this->json([
			'schedules'  => $helper->schedules,
			'schedule'   => $name ? $helper->schedules->get($name) : null,
			'transports' => $helper->transports->filter()->excludeSync()->excludeSchedules()->excludeFailed(),
		]);
	}

	#[ResponseSchema(schema: [
		'type'       => 'object',
		'required'   => ['success', 'message'],
		'properties' => [
			'success' => ['type' => 'boolean'],
			'message' => ['type' => 'string'],
		],
	])]
	public function triggerScheduleTask(
		string              $name,
		string              $id,
		string              $transport,
		ApiHelper           $helper,
		MessageBusInterface $bus
	): JsonResponse {
		if (!$helper->schedules) {
			throw new \LogicException('Scheduler must be configured to use the dashboard.');
		}

		$task = $helper->schedules->get($name)->task($id);

		$context = new MessageContext(
			$helper->schedules->get($name)->name(),
			$task->id(),
			$task->trigger()->get(),
			new \DateTimeImmutable(),
		);

		foreach ($task->get()->getMessages($context) as $message) {
			if ($message instanceof RedispatchMessage) {
				$message = $message->envelope;
			}

			$bus->dispatch($message, [
				new TagStamp('manual'),
				TagStamp::forSchedule($task),
				new TransportNamesStamp($transport),
			]);
		}

		return $this->json([
			'success' => true,
			'message' => \sprintf('Task "%s" triggered on "%s" transport.', $task->id(), $transport),
		]);
	}

	#[ResponseSchema(schema: [
		'type'       => 'object',
		'required'   => ['workers'],
		'properties' => [
			'workers' => ['type' => 'array'],
		],
	])]
	public function workersWidget(ApiHelper $helper): JsonResponse {
		return $this->json([
			'workers' => $helper->workers,
		]);
	}
}
