<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Model\AiAnalysis;
use App\Model\AnalysisSource;
use App\Model\ContactSubmission;
use App\Model\MessageCategory;
use App\Model\Sentiment;

/**
 * Keyword-based fallback that always works — the last link of the chain.
 * Deliberately simple: its job is to keep the service functional and give
 * a rough signal when no AI provider is reachable.
 */
final class HeuristicAnalyzer
{
    private const SPAM_MARKERS = [
        'http://', 'https://', 'casino', 'crypto', 'viagra', 'lottery', 'free money',
        'click here', 'заработок', 'казино', 'крипта', 'ставки', 'выигрыш',
    ];

    private const POSITIVE_MARKERS = [
        'спасибо', 'отлично', 'нравится', 'круто', 'здорово', 'рад',
        'great', 'love', 'excellent', 'awesome', 'thank',
    ];

    private const NEGATIVE_MARKERS = [
        'плохо', 'ужасно', 'недоволен', 'проблема', 'жалоба', 'разочарован',
        'bad', 'terrible', 'angry', 'issue', 'complaint', 'disappointed',
    ];

    // NB: no bare 'работ' here — it would match inside 'разработать' (project requests).
    private const JOB_MARKERS = [
        'ваканси', 'оффер', 'собеседовани', 'зарплат', 'наняти', 'найм',
        'job', 'vacancy', 'position', 'hire', 'offer', 'salary', 'interview',
    ];

    private const PROJECT_MARKERS = [
        'проект', 'сайт', 'приложени', 'разработ', 'интеграци', 'бэкенд', 'api',
        'project', 'website', 'application', 'develop', 'backend', 'integration',
    ];

    public function analyze(ContactSubmission $submission): AiAnalysis
    {
        $text = mb_strtolower($submission->comment.' '.$submission->name);

        $spamScore = min(1.0, 0.3 * $this->countMatches($text, self::SPAM_MARKERS));

        $positive = $this->countMatches($text, self::POSITIVE_MARKERS);
        $negative = $this->countMatches($text, self::NEGATIVE_MARKERS);
        $sentiment = match (true) {
            $positive > $negative => Sentiment::Positive,
            $negative > $positive => Sentiment::Negative,
            default => Sentiment::Neutral,
        };

        $category = match (true) {
            $spamScore >= 0.6 => MessageCategory::Spam,
            $this->countMatches($text, self::JOB_MARKERS) > 0 => MessageCategory::JobOffer,
            $this->countMatches($text, self::PROJECT_MARKERS) > 0 => MessageCategory::ProjectInquiry,
            str_contains($submission->comment, '?') => MessageCategory::Question,
            default => MessageCategory::Other,
        };

        return new AiAnalysis(
            sentiment: $sentiment,
            category: $category,
            spamScore: $spamScore,
            replyDraft: null,
            provider: 'heuristic',
            source: AnalysisSource::Heuristic,
        );
    }

    /**
     * @param list<string> $markers
     */
    private function countMatches(string $text, array $markers): int
    {
        $count = 0;
        foreach ($markers as $marker) {
            if (str_contains($text, $marker)) {
                ++$count;
            }
        }

        return $count;
    }
}
