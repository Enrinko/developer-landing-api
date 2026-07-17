<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\ContactRequest;
use App\Model\AiAnalysis;
use App\Model\AnalysisSource;
use App\Model\EmailStatus;
use App\Model\MessageCategory;
use App\Model\Sentiment;
use App\Repository\ContactSubmissionRepositoryInterface;
use App\Service\Ai\AiAnalyzerInterface;
use App\Service\ContactNotifierInterface;
use App\Service\ContactService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportException;

final class ContactServiceTest extends TestCase
{
    public function testEmailTransportFailureDoesNotLoseTheSubmission(): void
    {
        $repository = $this->createMock(ContactSubmissionRepositoryInterface::class);
        $repository->expects(self::once())->method('save');

        $notifier = $this->createStub(ContactNotifierInterface::class);
        $notifier->method('sendOwnerNotification')
            ->willThrowException(new TransportException('smtp down'));

        $service = new ContactService($repository, $notifier, $this->analyzer(), new NullLogger());

        $result = $service->submit($this->request(), '127.0.0.1');

        self::assertSame(EmailStatus::Failed, $result->emailStatus);
        self::assertSame('john@example.com', $result->submission->email);
    }

    public function testSuccessfulDispatchReportsEmailsSent(): void
    {
        $repository = $this->createMock(ContactSubmissionRepositoryInterface::class);
        $repository->expects(self::once())->method('save');

        $notifier = $this->createMock(ContactNotifierInterface::class);
        $notifier->expects(self::once())->method('sendOwnerNotification');
        $notifier->expects(self::once())->method('sendUserConfirmation');

        $service = new ContactService($repository, $notifier, $this->analyzer(), new NullLogger());

        $result = $service->submit($this->request(), '127.0.0.1');

        self::assertSame(EmailStatus::Sent, $result->emailStatus);
    }

    public function testSubmissionIsEnrichedWithAiAnalysisBeforePersisting(): void
    {
        $repository = $this->createMock(ContactSubmissionRepositoryInterface::class);
        $repository->expects(self::once())->method('save')
            ->with(self::callback(static fn ($submission) => null !== $submission->analysis));

        $service = new ContactService(
            $repository,
            $this->createStub(ContactNotifierInterface::class),
            $this->analyzer(),
            new NullLogger(),
        );

        $result = $service->submit($this->request(), '127.0.0.1');

        self::assertNotNull($result->submission->analysis);
        self::assertSame('stub', $result->submission->analysis->provider);
    }

    private function analyzer(): AiAnalyzerInterface
    {
        $analyzer = $this->createStub(AiAnalyzerInterface::class);
        $analyzer->method('analyze')->willReturn(new AiAnalysis(
            sentiment: Sentiment::Neutral,
            category: MessageCategory::Other,
            spamScore: 0.0,
            replyDraft: null,
            provider: 'stub',
            source: AnalysisSource::Ai,
        ));

        return $analyzer;
    }

    private function request(): ContactRequest
    {
        return new ContactRequest(
            name: 'John Doe',
            email: 'john@example.com',
            phone: '+1 234 567 890',
            comment: 'Interested in working with you.',
        );
    }
}
