<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContactRequest;
use App\Model\ContactResult;
use App\Model\ContactSubmission;
use App\Model\EmailStatus;
use App\Repository\ContactSubmissionRepositoryInterface;
use App\Service\Ai\AiAnalyzerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Application layer: orchestrates the full cycle of a validated contact
 * request: analyze (AI) -> persist -> notify -> respond.
 */
final class ContactService
{
    public function __construct(
        private readonly ContactSubmissionRepositoryInterface $repository,
        private readonly ContactNotifierInterface $notifier,
        private readonly AiAnalyzerInterface $aiAnalyzer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function submit(ContactRequest $request, string $ip): ContactResult
    {
        $submission = ContactSubmission::fromRequest($request, $ip);

        // Never throws: falls back to a heuristic analysis when AI is down.
        $submission = $submission->withAnalysis($this->aiAnalyzer->analyze($submission));

        $this->repository->save($submission);

        $emailStatus = $this->dispatchEmails($submission);

        $this->logger->info('Contact submission accepted', [
            'id' => $submission->id,
            'email' => $submission->email,
            'emails' => $emailStatus->value,
        ]);

        return new ContactResult($submission, $emailStatus);
    }

    /**
     * A broken SMTP transport must not lose the submission: it is already
     * persisted, so we log the failure and report it in the response instead
     * of failing the whole request.
     */
    private function dispatchEmails(ContactSubmission $submission): EmailStatus
    {
        try {
            $this->notifier->sendOwnerNotification($submission);
            $this->notifier->sendUserConfirmation($submission);

            return EmailStatus::Sent;
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Failed to send contact notification emails', [
                'id' => $submission->id,
                'exception' => $exception,
            ]);

            return EmailStatus::Failed;
        }
    }
}
