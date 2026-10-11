<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Enforces a sliding-window quota on the API surface.
 *
 * Authenticated callers are limited per identity, anonymous ones per IP, so a
 * shared NAT cannot exhaust somebody else's budget. The limiter is advisory
 * only in the sense that it protects against runaway clients — an edge WAF is
 * still the right place for volumetric attacks.
 *
 * Priority 7 places it right after the firewall (8): any earlier and the token
 * storage is still empty, so every caller would be limited per IP. Requests the
 * firewall rejects (bad credentials, missing token) never reach this listener;
 * `/api/login` is covered by `login_throttling` instead.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onRequest', priority: 7)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse', priority: -255)]
final readonly class ApiRateLimitSubscriber
{
    private const string ATTRIBUTE = '_api_rate_limit';

    public function __construct(
        #[Autowire(service: 'limiter.api')]
        private RateLimiterFactoryInterface $limiter,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $limit = $this->limiter->create($this->identify($request))->consume();
        $request->attributes->set(self::ATTRIBUTE, $limit);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'API rate limit exceeded.');
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $limit = $event->getRequest()->attributes->get(self::ATTRIBUTE);

        if (!$limit instanceof RateLimit) {
            return;
        }

        $event->getResponse()->headers->add([
            'X-RateLimit-Limit' => (string) $limit->getLimit(),
            'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
            'X-RateLimit-Reset' => (string) $limit->getRetryAfter()->getTimestamp(),
        ]);
    }

    private function identify(Request $request): string
    {
        $user = $this->tokenStorage->getToken()?->getUserIdentifier();

        return null !== $user && '' !== $user ? 'user:'.$user : 'ip:'.$request->getClientIp();
    }
}
