<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Service\Ai\AiResponseParser;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Local Ollama through its OpenAI-compatible endpoint — fully free and
 * offline. Run: `ollama serve` + `ollama pull llama3.2`, then set
 * OLLAMA_BASE_URL=http://host.docker.internal:11434/v1.
 */
#[AutoconfigureTag(name: 'app.ai_provider', attributes: ['priority' => 60])]
final class OllamaProvider extends OpenAiCompatibleProvider
{
    public function __construct(
        HttpClientInterface $httpClient,
        AiResponseParser $parser,
        #[Autowire('%env(OLLAMA_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(OLLAMA_MODEL)%')]
        private readonly string $model,
        #[Autowire('%env(int:AI_TIMEOUT_SECONDS)%')]
        int $timeoutSeconds,
    ) {
        parent::__construct($httpClient, $parser, $timeoutSeconds);
    }

    public function name(): string
    {
        return 'ollama';
    }

    public function isConfigured(): bool
    {
        return '' !== $this->baseUrl;
    }

    protected function baseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function apiKey(): string
    {
        return '';
    }

    protected function model(): string
    {
        return $this->model;
    }

    protected function supportsJsonResponseFormat(): bool
    {
        return false;
    }
}
