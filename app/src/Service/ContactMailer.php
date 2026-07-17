<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\ContactSubmission;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ContactMailer implements ContactNotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAIL_FROM)%')]
        private readonly string $fromAddress,
        #[Autowire('%env(MAIL_FROM_NAME)%')]
        private readonly string $fromName,
        #[Autowire('%env(MAIL_OWNER)%')]
        private readonly string $ownerAddress,
    ) {
    }

    public function sendOwnerNotification(ContactSubmission $submission): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($this->ownerAddress)
            ->replyTo(new Address($submission->email, $submission->name))
            ->subject(\sprintf('Новое обращение с сайта — %s', $submission->name))
            ->htmlTemplate('emails/owner_notification.html.twig')
            ->context(['submission' => $submission]);

        $this->mailer->send($email);
    }

    public function sendUserConfirmation(ContactSubmission $submission): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($submission->email, $submission->name))
            ->subject('Ваше обращение получено')
            ->htmlTemplate('emails/user_confirmation.html.twig')
            ->context(['submission' => $submission]);

        $this->mailer->send($email);
    }
}
