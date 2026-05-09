<?php

namespace Respatch\RespatchBundle\Helper;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
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
		private readonly ?CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function storage(): Storage
    {
        return $this->storage ?? throw new \LogicException('Storage is not enabled.');
    }

	public function generateCsrfToken(string ...$parts): string
	{
		if (!$this->csrfTokenManager) {
			return '';
		}

		return $this->csrfTokenManager->getToken(self::csrfTokenId(...$parts));
	}

	public function validateCsrfToken(string $token, string ...$parts): void
	{
		if (!$this->csrfTokenManager) {
			return;
		}

		if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::csrfTokenId(...$parts), $token))) {
			throw new HttpException(419, 'Invalid CSRF token.');
		}
	}

	private static function csrfTokenId(string ...$parts): string
	{
		return \implode('-', $parts);
	}

}
