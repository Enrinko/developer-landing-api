<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Dto\ContactRequest;
use App\Model\ContactSubmission;
use App\Model\MessageCategory;
use App\Model\Sentiment;
use App\Service\Ai\HeuristicAnalyzer;
use PHPUnit\Framework\TestCase;

final class HeuristicAnalyzerTest extends TestCase
{
    private HeuristicAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new HeuristicAnalyzer();
    }

    public function testDetectsSpam(): void
    {
        $analysis = $this->analyzer->analyze($this->submission(
            'Win free money at our casino! Click here https://spam.example.com',
        ));

        self::assertSame(MessageCategory::Spam, $analysis->category);
        self::assertGreaterThanOrEqual(0.6, $analysis->spamScore);
    }

    public function testDetectsJobOfferWithPositiveSentiment(): void
    {
        $analysis = $this->analyzer->analyze($this->submission(
            'Здравствуйте! У нас есть отличная вакансия backend-разработчика, спасибо за ваш профиль.',
        ));

        self::assertSame(MessageCategory::JobOffer, $analysis->category);
        self::assertSame(Sentiment::Positive, $analysis->sentiment);
    }

    public function testDetectsProjectInquiry(): void
    {
        $analysis = $this->analyzer->analyze($this->submission(
            'Нужно разработать сайт с интеграцией платежей.',
        ));

        self::assertSame(MessageCategory::ProjectInquiry, $analysis->category);
    }

    public function testPlainQuestionFallsIntoQuestionCategory(): void
    {
        $analysis = $this->analyzer->analyze($this->submission('Сколько стоит час консультации?'));

        self::assertSame(MessageCategory::Question, $analysis->category);
    }

    private function submission(string $comment): ContactSubmission
    {
        return ContactSubmission::fromRequest(
            new ContactRequest('Test User', 'test@example.com', '+7 900 000-00-00', $comment),
            '127.0.0.1',
        );
    }
}
