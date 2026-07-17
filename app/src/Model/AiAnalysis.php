<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Result of analyzing one contact message: sentiment, request category,
 * spam likelihood and a suggested reply draft for the site owner.
 */
final readonly class AiAnalysis
{
    public function __construct(
        public Sentiment $sentiment,
        public MessageCategory $category,
        public float $spamScore,
        public ?string $replyDraft,
        public string $provider,
        public AnalysisSource $source,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sentiment' => $this->sentiment->value,
            'category' => $this->category->value,
            'spamScore' => $this->spamScore,
            'replyDraft' => $this->replyDraft,
            'provider' => $this->provider,
            'source' => $this->source->value,
        ];
    }
}
