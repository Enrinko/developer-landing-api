<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Dto\ContactRequest;
use App\Exception\AiProviderException;
use App\Model\AiAnalysis;
use App\Model\AnalysisSource;
use App\Model\ContactSubmission;
use App\Model\MessageCategory;
use App\Model\Sentiment;
use App\Service\Ai\AiProviderInterface;
use App\Service\Ai\ChainAiAnalyzer;
use App\Service\Ai\HeuristicAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ChainAiAnalyzerTest extends TestCase
{
    public function testFailingProviderFallsThroughToTheNextOne(): void
    {
        $chain = new ChainAiAnalyzer(
            [
                $this->provider('first', configured: true, result: null),
                $this->provider('second', configured: true, result: $this->analysis('second')),
            ],
            new HeuristicAnalyzer(),
            new NullLogger(),
            'auto',
        );

        $analysis = $chain->analyze($this->submission());

        self::assertSame('second', $analysis->provider);
        self::assertSame(AnalysisSource::Ai, $analysis->source);
    }

    public function testUnconfiguredProvidersAreSkipped(): void
    {
        $chain = new ChainAiAnalyzer(
            [
                $this->provider('first', configured: false, result: $this->analysis('first')),
                $this->provider('second', configured: true, result: $this->analysis('second')),
            ],
            new HeuristicAnalyzer(),
            new NullLogger(),
            'auto',
        );

        self::assertSame('second', $chain->analyze($this->submission())->provider);
    }

    public function testExplicitProviderSelectionIgnoresOthers(): void
    {
        $chain = new ChainAiAnalyzer(
            [
                $this->provider('first', configured: true, result: $this->analysis('first')),
                $this->provider('second', configured: true, result: $this->analysis('second')),
            ],
            new HeuristicAnalyzer(),
            new NullLogger(),
            'second',
        );

        self::assertSame('second', $chain->analyze($this->submission())->provider);
    }

    public function testHeuristicFallbackWhenEveryProviderFails(): void
    {
        $chain = new ChainAiAnalyzer(
            [
                $this->provider('first', configured: true, result: null),
                $this->provider('second', configured: true, result: null),
            ],
            new HeuristicAnalyzer(),
            new NullLogger(),
            'auto',
        );

        $analysis = $chain->analyze($this->submission());

        self::assertSame(AnalysisSource::Heuristic, $analysis->source);
        self::assertSame('heuristic', $analysis->provider);
    }

    public function testOffDisablesAiCompletely(): void
    {
        $chain = new ChainAiAnalyzer(
            [$this->provider('first', configured: true, result: $this->analysis('first'))],
            new HeuristicAnalyzer(),
            new NullLogger(),
            'off',
        );

        self::assertSame(AnalysisSource::Heuristic, $chain->analyze($this->submission())->source);
    }

    private function provider(string $name, bool $configured, ?AiAnalysis $result): AiProviderInterface
    {
        return new class($name, $configured, $result) implements AiProviderInterface {
            public function __construct(
                private readonly string $providerName,
                private readonly bool $configured,
                private readonly ?AiAnalysis $result,
            ) {
            }

            public function name(): string
            {
                return $this->providerName;
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function analyze(ContactSubmission $submission): AiAnalysis
            {
                return $this->result ?? throw new AiProviderException($this->providerName.' is down');
            }
        };
    }

    private function analysis(string $provider): AiAnalysis
    {
        return new AiAnalysis(
            sentiment: Sentiment::Neutral,
            category: MessageCategory::Other,
            spamScore: 0.0,
            replyDraft: null,
            provider: $provider,
            source: AnalysisSource::Ai,
        );
    }

    private function submission(): ContactSubmission
    {
        return ContactSubmission::fromRequest(
            new ContactRequest('John', 'john@example.com', '+1 234 567', 'Hello there, nice site.'),
            '127.0.0.1',
        );
    }
}
