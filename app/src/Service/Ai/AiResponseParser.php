<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Exception\AiProviderException;
use App\Model\AiAnalysis;
use App\Model\AnalysisSource;
use App\Model\MessageCategory;
use App\Model\Sentiment;

/**
 * Turns a model's text answer into a typed AiAnalysis. Tolerant to common
 * LLM quirks (markdown fences, unknown enum values, out-of-range scores).
 */
final class AiResponseParser
{
    public function parse(string $text, string $provider): AiAnalysis
    {
        $json = $this->extractJson($text);

        try {
            $data = json_decode($json, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new AiProviderException(\sprintf('Provider "%s" returned a non-JSON analysis.', $provider), previous: $exception);
        }

        if (!\is_array($data)) {
            throw new AiProviderException(\sprintf('Provider "%s" returned an unexpected JSON shape.', $provider));
        }

        $sentiment = Sentiment::tryFrom(strtolower((string) ($data['sentiment'] ?? ''))) ?? Sentiment::Neutral;
        $category = MessageCategory::tryFrom(strtolower((string) ($data['category'] ?? ''))) ?? MessageCategory::Other;

        $spamScore = $data['spam_score'] ?? 0.0;
        $spamScore = is_numeric($spamScore) ? (float) $spamScore : 0.0;
        $spamScore = max(0.0, min(1.0, $spamScore));

        $replyDraft = isset($data['reply_draft']) && \is_string($data['reply_draft'])
            ? trim($data['reply_draft'])
            : null;

        return new AiAnalysis(
            sentiment: $sentiment,
            category: $category,
            spamScore: $spamScore,
            replyDraft: '' === $replyDraft ? null : $replyDraft,
            provider: $provider,
            source: AnalysisSource::Ai,
        );
    }

    private function extractJson(string $text): string
    {
        if (1 === preg_match('/```(?:json)?\s*(.+?)```/s', $text, $matches)) {
            return trim($matches[1]);
        }

        return trim($text);
    }
}
