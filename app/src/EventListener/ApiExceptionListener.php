<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Global error handler for the API surface: every throwable thrown under /api
 * is rendered as one JSON envelope: {"message": string, "errors"?: object}.
 * Status codes are mapped here and nowhere else.
 */
#[AsEventListener(event: ExceptionEvent::class)]
final class ApiExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();

        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = [];
        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $headers = $throwable->getHeaders();
        }

        if ($status >= 500) {
            $this->logger->error('Unhandled API exception', ['exception' => $throwable]);
        }

        $payload = ['message' => $this->messageFor($status, $throwable)];

        $validationFailure = $this->findValidationFailure($throwable);
        if (null !== $validationFailure) {
            $payload['errors'] = $this->formatViolations($validationFailure);
        }

        $event->setResponse(new JsonResponse($payload, $status, $headers));
        $event->stopPropagation();
    }

    private function messageFor(int $status, \Throwable $throwable): string
    {
        return match (true) {
            Response::HTTP_NOT_FOUND === $status => 'Resource not found',
            Response::HTTP_METHOD_NOT_ALLOWED === $status => 'Method not allowed',
            Response::HTTP_UNPROCESSABLE_ENTITY === $status => 'Validation failed',
            Response::HTTP_TOO_MANY_REQUESTS === $status => 'Too many requests. Please try again later.',
            Response::HTTP_BAD_REQUEST === $status => 'Malformed request body',
            $status < 500 => $throwable->getMessage(),
            // Never leak internals for 5xx.
            default => 'Internal server error',
        };
    }

    private function findValidationFailure(\Throwable $throwable): ?ValidationFailedException
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function formatViolations(ValidationFailedException $exception): array
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[$violation->getPropertyPath()][] = (string) $violation->getMessage();
        }

        return $errors;
    }
}
