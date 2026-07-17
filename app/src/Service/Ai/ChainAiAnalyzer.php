<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Exception\AiProviderException;
use App\Model\AiAnalysis;
use App\Model\ContactSubmission;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Chain of Responsibility over AI providers.
 *
 * AI_PROVIDER env selects the strategy:
 *  - "auto"          — try every configured provider in priority order;
 *  - "<name>"        — use only that provider (gemini|anthropic|openai|groq|ollama);
 *  - "off"           — skip AI entirely.
 * Whatever happens, the heuristic fallback guarantees a result — the
 * contact endpoint keeps working when every AI provider is down.
 */
final class ChainAiAnalyzer implements AiAnalyzerInterface
{
    /**
     * @param iterable<AiProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.ai_provider')]
        private readonly iterable $providers,
        private readonly HeuristicAnalyzer $heuristic,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(AI_PROVIDER)%')]
        private readonly string $preferredProvider,
    ) {
    }

    public function analyze(ContactSubmission $submission): AiAnalysis
    {
        foreach ($this->candidates() as $provider) {
            try {
                $analysis = $provider->analyze($submission);
                $this->logger->info('AI analysis completed', [
                    'provider' => $provider->name(),
                    'submission' => $submission->id,
                ]);

                return $analysis;
            } catch (AiProviderException $exception) {
                $this->logger->warning('AI provider failed, trying next', [
                    'provider' => $provider->name(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->logger->info('No AI provider available, using heuristic fallback', [
            'submission' => $submission->id,
        ]);

        return $this->heuristic->analyze($submission);
    }

    /**
     * @return list<AiProviderInterface>
     */
    private function candidates(): array
    {
        $preferred = strtolower(trim($this->preferredProvider));
        if ('off' === $preferred || 'none' === $preferred) {
            return [];
        }

        $candidates = [];
        foreach ($this->providers as $provider) {
            if (!$provider->isConfigured()) {
                continue;
            }
            if ('auto' !== $preferred && $provider->name() !== $preferred) {
                continue;
            }
            $candidates[] = $provider;
        }

        return $candidates;
    }
}
