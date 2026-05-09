<?php

namespace Respatch\RespatchBundle\Helper;

use Symfony\Component\HttpKernel\Exception\HttpException;
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
        public readonly ?Schedules $schedules,
		private readonly string $appSecret,
    ) {
    }

    public function storage(): Storage
    {
        return $this->storage ?? throw new \LogicException('Storage is not enabled.');
    }

	public function generateCsrfToken(string ...$parts): string
	{
		return hash_hmac('sha256', self::csrfTokenId(...$parts), $this->appSecret);
	}

	public function validateCsrfToken(string $token, string ...$parts): void
	{
		$expected = $this->generateCsrfToken(...$parts);
		if (!hash_equals($expected, $token)) {
			throw new HttpException(419, 'Invalid CSRF token.');
		}
	}

	private static function csrfTokenId(string ...$parts): string
	{
		return \implode('-', $parts);
	}

}
