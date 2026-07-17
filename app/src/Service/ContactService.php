<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContactRequest;
use App\Model\ContactSubmission;
use App\Repository\ContactSubmissionRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Application layer: orchestrates what happens to a validated
 * contact request (persist -> notify -> respond).
 */
final class ContactService
{
    public function __construct(
        private readonly ContactSubmissionRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function submit(ContactRequest $request, string $ip): ContactSubmission
    {
        $submission = ContactSubmission::fromRequest($request, $ip);

        $this->repository->save($submission);

        $this->logger->info('Contact submission accepted', [
            'id' => $submission->id,
            'email' => $submission->email,
        ]);

        return $submission;
    }
}
