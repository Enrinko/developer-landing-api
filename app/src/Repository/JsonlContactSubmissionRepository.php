<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\StorageUnavailableException;
use App\Model\ContactSubmission;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Appends submissions to a JSON Lines file under an exclusive lock,
 * so concurrent requests never interleave records.
 */
final class JsonlContactSubmissionRepository implements ContactSubmissionRepositoryInterface
{
    private const FILE_NAME = 'submissions.jsonl';

    public function __construct(
        #[Autowire('%app.storage_dir%')]
        private readonly string $storageDir,
    ) {
    }

    public function save(ContactSubmission $submission): void
    {
        $this->ensureStorageDir();

        $file = $this->storageDir.\DIRECTORY_SEPARATOR.self::FILE_NAME;
        $handle = @fopen($file, 'ab');
        if (false === $handle) {
            throw new StorageUnavailableException(\sprintf('Cannot open storage file "%s" for writing.', $file));
        }

        try {
            if (!flock($handle, \LOCK_EX)) {
                throw new StorageUnavailableException(\sprintf('Cannot acquire lock on storage file "%s".', $file));
            }

            $line = json_encode($submission->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)."\n";
            if (false === fwrite($handle, $line)) {
                throw new StorageUnavailableException(\sprintf('Cannot write to storage file "%s".', $file));
            }

            flock($handle, \LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public function readAll(): iterable
    {
        $file = $this->storageDir.\DIRECTORY_SEPARATOR.self::FILE_NAME;
        if (!is_file($file)) {
            return;
        }

        $handle = @fopen($file, 'rb');
        if (false === $handle) {
            return;
        }

        try {
            while (false !== ($line = fgets($handle))) {
                $line = trim($line);
                if ('' === $line) {
                    continue;
                }

                $record = json_decode($line, true);
                if (\is_array($record)) {
                    yield $record;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function ensureStorageDir(): void
    {
        if (is_dir($this->storageDir)) {
            return;
        }

        if (!@mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            throw new StorageUnavailableException(\sprintf('Cannot create storage directory "%s".', $this->storageDir));
        }
    }
}
