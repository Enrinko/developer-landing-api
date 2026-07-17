<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Exception\AiProviderException;
use App\Model\AnalysisSource;
use App\Model\MessageCategory;
use App\Model\Sentiment;
use App\Service\Ai\AiResponseParser;
use PHPUnit\Framework\TestCase;

final class AiResponseParserTest extends TestCase
{
    private AiResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AiResponseParser();
    }

    public function testParsesPlainJsonAnswer(): void
    {
        $analysis = $this->parser->parse(
            '{"sentiment":"positive","category":"job_offer","spam_score":0.15,"reply_draft":"Спасибо за предложение!"}',
            'gemini',
        );

        self::assertSame(Sentiment::Positive, $analysis->sentiment);
        self::assertSame(MessageCategory::JobOffer, $analysis->category);
        self::assertSame(0.15, $analysis->spamScore);
        self::assertSame('Спасибо за предложение!', $analysis->replyDraft);
        self::assertSame('gemini', $analysis->provider);
        self::assertSame(AnalysisSource::Ai, $analysis->source);
    }

    public function testStripsMarkdownFences(): void
    {
        $text = "```json\n{\"sentiment\":\"negative\",\"category\":\"question\",\"spam_score\":0}\n```";

        $analysis = $this->parser->parse($text, 'ollama');

        self::assertSame(Sentiment::Negative, $analysis->sentiment);
        self::assertSame(MessageCategory::Question, $analysis->category);
    }

    public function testUnknownEnumValuesFallBackToDefaults(): void
    {
        $analysis = $this->parser->parse(
            '{"sentiment":"ecstatic","category":"weird","spam_score":"not-a-number"}',
            'openai',
        );

        self::assertSame(Sentiment::Neutral, $analysis->sentiment);
        self::assertSame(MessageCategory::Other, $analysis->category);
        self::assertSame(0.0, $analysis->spamScore);
        self::assertNull($analysis->replyDraft);
    }

    public function testSpamScoreIsClampedToRange(): void
    {
        $analysis = $this->parser->parse('{"sentiment":"neutral","category":"other","spam_score":5}', 'groq');

        self::assertSame(1.0, $analysis->spamScore);
    }

    public function testNonJsonAnswerThrows(): void
    {
        $this->expectException(AiProviderException::class);

        $this->parser->parse('Sorry, I cannot help with that.', 'anthropic');
    }
}
