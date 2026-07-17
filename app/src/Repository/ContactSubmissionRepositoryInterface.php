<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\StorageUnavailableException;
use App\Model\ContactSubmission;

/**
 * Persistence seam: the file-backed implementation can be swapped
 * for a database one without touching the service layer.
 */
interface ContactSubmissionRepositoryInterface
{
    /**
     * @throws StorageUnavailableException
     */
    public function save(ContactSubmission $submission): void;
}
