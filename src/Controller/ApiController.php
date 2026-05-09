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


	public function status(): JsonResponse {
		return $this->json([
			'status' => 'OK',
		]);
	}


	public function recentMessages(Request $request, ApiHelper $helper): JsonResponse {
		$messages = Specification::new()->snapshot($helper->storage())->messages()->take(25);
		$result   = $messages->map(fn(ProcessedMessage $message) => [
			"id"        => $message->id(),
			"title"     => $message->type()->class(),
			"status"    => $message->failure()?->description(),
			"transport" => $message->transport(),
			"class"     => $message->type()->class(),
			"duration"  => $message->timeToProcess(),
			"memory"    => $message->memoryUsage()->format(),
			"handledAt" => $message->finishedAt()->format(DateTimeInterface::ATOM),
		]);

		return $this->json($result);
	}

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
					"class"       => $message->message()->class(),
					"transport"   => $name,
					"dispatched"  => $message->dispatchedAt()->format(DateTimeInterface::ATOM),
					"deleteToken" => $helper->generateCsrfToken('remove', $name, (string) $message->id()),
					"retryToken"  => $helper->generateCsrfToken('retry', $name, (string) $message->id()),
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

}
