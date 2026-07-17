<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\ContactRequest;
use App\Model\EmailStatus;
use App\Repository\ContactSubmissionRepositoryInterface;
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

        $service = new ContactService($repository, $notifier, new NullLogger());

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

        $service = new ContactService($repository, $notifier, new NullLogger());

        $result = $service->submit($this->request(), '127.0.0.1');

        self::assertSame(EmailStatus::Sent, $result->emailStatus);
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
