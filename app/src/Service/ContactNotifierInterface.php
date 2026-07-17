<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\ContactSubmission;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

interface ContactNotifierInterface
{
    /**
     * @throws TransportExceptionInterface
     */
    public function sendOwnerNotification(ContactSubmission $submission): void;

    /**
     * @throws TransportExceptionInterface
     */
    public function sendUserConfirmation(ContactSubmission $submission): void;
}
