<?php

namespace Respatch\RespatchBundle\Controller;

use Respatch\RespatchBundle\Helper\ApiHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Zenstruck\Messenger\Monitor\History\Period;
use Zenstruck\Messenger\Monitor\History\Specification;
use Zenstruck\Messenger\Monitor\Stamp\TagStamp;

class ApiController extends AbstractController
{
    public function status(): JsonResponse
    {
        return $this->json([
            'status' => 'OK',
        ]);
    }

    public function dashboard(ApiHelper $helper): JsonResponse
    {
        return $this->json([
            'snapshot' => Specification::create(Period::IN_LAST_DAY)->snapshot($helper->storage()),
            'messages' => Specification::new()->snapshot($helper->storage())->messages(),
        ]);
    }

    public function statistics(Request $request, ApiHelper $helper): JsonResponse
    {
        $period = Period::parse($request->query->getString('period'));
        $specification = Specification::create([
            'period' => $period,
        ]);

        return $this->json([
            'periods' => [...Period::inLastCases(), ...Period::absoluteCases()],
            'period' => $period,
            'metrics' => $specification->snapshot($helper->storage())->perMessageTypeMetrics(),
        ]);
    }

    public function history(Request $request, ApiHelper $helper): JsonResponse
    {
        $tags = [$request->query->get('tag')];
        $notTags = [];
        $period = Period::parse($request->query->getString('period'));

        match ($schedule = $request->query->get('schedule')) {
            '_exclude' => $notTags[] = 'schedule',
            '_include' => null,
            default => $tags[] = $schedule,
        };

        $specification = Specification::create([
            'period' => $period,
            'transport' => $request->query->get('transport'),
            'status' => $request->query->get('status'),
            'tags' => \array_filter($tags),
            'not_tags' => $notTags,
            'message_type' => $request->query->get('type'),
        ]);

        return $this->json([
            'periods' => [...Period::inLastCases(), ...Period::absoluteCases()],
            'period' => $period,
            'snapshot' => $specification->snapshot($helper->storage()),
            'filters' => $specification->filters($helper->storage()),
        ]);
    }

    public function detail(string $id, ApiHelper $helper): JsonResponse
    {
        if (!$message = $helper->storage()->find($id)) {
            throw $this->createNotFoundException('Message not found.');
        }

        return $this->json([
            'message' => $message,
            'other_attempts' => $helper->storage()->filter(Specification::create(['run_id' => $message->runId()])),
        ]);
    }

    public function transports(ApiHelper $helper, Request $request, ?string $name = null): JsonResponse
    {
        $countable = $helper->transports->filter()->countable();

        if (!\count($countable)) {
            throw new \LogicException('No countable transports configured.');
        }

        if (!$name) {
            $name = $countable->names()[0] ?? null;
        }

        return $this->json([
            'transports' => $countable,
            'transport' => $name ? $helper->transports->get($name) : null,
            'limit' => $request->query->getInt('limit', 50),
        ]);
    }

    public function removeTransportMessage(string $name, string $id, ApiHelper $helper): JsonResponse
    {
        $transport = $helper->transports->get($name);
        $message = $transport->find($id) ?? throw $this->createNotFoundException('Message not found.');

        $transport->get()->reject($message->envelope());

        return $this->json([
            'success' => true,
            'message' => \sprintf('Message "%s" removed from transport "%s".', $message->message()->shortName(), $name),
        ]);
    }

    public function retryFailedMessage(string $name, string $id, ApiHelper $helper, MessageBusInterface $bus): JsonResponse
    {
        $transport = $helper->transports->get($name);
        $message = $transport->find($id) ?? throw $this->createNotFoundException('Message not found.');
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

    public function schedules(ApiHelper $helper, ?string $name = null): JsonResponse
    {
        if (!$helper->schedules) {
            throw new \LogicException('Scheduler must be configured to use the dashboard.');
        }

        if (!\count($helper->schedules)) {
            throw new \LogicException('No schedules configured.');
        }

        return $this->json([
            'schedules' => $helper->schedules,
            'schedule' => $name ? $helper->schedules->get($name) : null,
            'transports' => $helper->transports->filter()->excludeSync()->excludeSchedules()->excludeFailed(),
        ]);
    }

    public function triggerScheduleTask(
        string $name,
        string $id,
        string $transport,
        ApiHelper $helper,
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

    public function workersWidget(ApiHelper $helper): JsonResponse
    {
        return $this->json([
            'workers' => $helper->workers,
        ]);
    }
}
