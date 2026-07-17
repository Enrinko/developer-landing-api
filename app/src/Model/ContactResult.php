<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Outcome of handling one contact request, as reported to the API client.
 */
final readonly class ContactResult
{
    public function __construct(
        public ContactSubmission $submission,
        public EmailStatus $emailStatus,
    ) {
    }
}
