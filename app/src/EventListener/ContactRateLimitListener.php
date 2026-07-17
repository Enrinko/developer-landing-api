<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Spam protection for the contact form: sliding window per client IP,
 * enforced before validation and business logic even run. Exceeding the
 * limit yields 429 + Retry-After via the global ApiExceptionListener.
 */
#[AsEventListener(event: RequestEvent::class, priority: 8)]
final class ContactRateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $contactFormLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('api_contact_create' !== $request->attributes->get('_route')) {
            return;
        }

        $limit = $this->contactFormLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw new TooManyRequestsHttpException($retryAfter);
        }
    }
}
