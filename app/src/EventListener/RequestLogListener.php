<?php

declare(strict_types=1);

namespace App\EventListener;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Logs every finished HTTP request to var/log/requests.log (JSON lines).
 * Runs on kernel.terminate so logging never delays the response.
 */
#[AsEventListener(event: TerminateEvent::class)]
#[WithMonologChannel('requests')]
final class RequestLogListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $startedAt = $request->server->get('REQUEST_TIME_FLOAT');
        $durationMs = null !== $startedAt
            ? (int) round((microtime(true) - (float) $startedAt) * 1000)
            : null;

        $this->logger->info('http_request', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status' => $response->getStatusCode(),
            'ip' => $request->getClientIp(),
            'duration_ms' => $durationMs,
            'user_agent' => $request->headers->get('User-Agent'),
        ]);
    }
}
