<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ContactSubmissionRepositoryInterface;

/**
 * Aggregates submission statistics straight from the JSONL storage —
 * the file IS the source of truth, so metrics can never drift from data.
 */
final class MetricsCalculator
{
    public function __construct(
        private readonly ContactSubmissionRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $total = 0;
        $bySentiment = [];
        $byCategory = [];
        $byAnalysisSource = [];
        $lastSubmissionAt = null;

        foreach ($this->repository->readAll() as $record) {
            ++$total;

            $ai = \is_array($record['ai'] ?? null) ? $record['ai'] : [];
            $this->increment($bySentiment, $ai['sentiment'] ?? 'unknown');
            $this->increment($byCategory, $ai['category'] ?? 'unknown');
            $this->increment($byAnalysisSource, $ai['source'] ?? 'unknown');

            if (\is_string($record['createdAt'] ?? null)) {
                $lastSubmissionAt = max($lastSubmissionAt ?? '', $record['createdAt']);
            }
        }

        return [
            'totalSubmissions' => $total,
            'bySentiment' => $bySentiment,
            'byCategory' => $byCategory,
            'byAnalysisSource' => $byAnalysisSource,
            'lastSubmissionAt' => $lastSubmissionAt,
        ];
    }

    /**
     * @param array<string, int> $counters
     */
    private function increment(array &$counters, mixed $key): void
    {
        $key = \is_string($key) && '' !== $key ? $key : 'unknown';
        $counters[$key] = ($counters[$key] ?? 0) + 1;
    }
}
