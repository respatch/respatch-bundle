<?php

namespace Respatch\RespatchBundle\Helper;

use Zenstruck\Messenger\Monitor\History\Storage;
use Zenstruck\Messenger\Monitor\Schedules;
use Zenstruck\Messenger\Monitor\Transports;
use Zenstruck\Messenger\Monitor\Workers;

class ApiHelper
{
    public function __construct(
        public readonly Transports $transports,
        public readonly Workers $workers,
        private readonly ?Storage $storage,
        public readonly ?Schedules $schedules
    ) {
    }

    public function storage(): Storage
    {
        return $this->storage ?? throw new \LogicException('Storage is not enabled.');
    }
}
