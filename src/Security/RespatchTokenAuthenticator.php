<?php
/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/Respatch/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respatch\RespatchBundle\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class RespatchTokenAuthenticator extends AbstractAuthenticator
{
	public function __construct(
		private string $configuredToken
	) {}

	public function supports(Request $request): ?bool
	{
		// Spustí sa to len pre naše API endpointy
		return str_starts_with($request->getPathInfo(), '/_respatch/api');
	}

	public function authenticate(Request $request): Passport
	{
		$headerToken = $request->headers->get('X-Respatch-Token');

		if (empty($this->configuredToken)) {
			throw new CustomUserMessageAuthenticationException('Respatch token is not configured on the server.');
		}

		if (null === $headerToken || !hash_equals($this->configuredToken, $headerToken)) {
			throw new CustomUserMessageAuthenticationException('Invalid or missing Respatch token.');
		}

		return new SelfValidatingPassport(new UserBadge('respatch_client', function() {
			return new InMemoryUser('respatch_client', null, ['ROLE_RESPATCH']);
		}));
	}

	public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
	{
		return null;
	}

	public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
	{
		return new JsonResponse(['error' => $exception->getMessageKey()], Response::HTTP_UNAUTHORIZED);
	}
}