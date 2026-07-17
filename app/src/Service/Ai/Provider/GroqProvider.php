<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Service\Ai\AiResponseParser;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Groq (Llama and other open models, generous free tier).
 * API key: https://console.groq.com/keys.
 */
#[AutoconfigureTag(name: 'app.ai_provider', attributes: ['priority' => 90])]
final class GroqProvider extends OpenAiCompatibleProvider
{
    private const BASE_URL = 'https://api.groq.com/openai/v1';

    public function __construct(
        HttpClientInterface $httpClient,
        AiResponseParser $parser,
        #[Autowire('%env(GROQ_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(GROQ_MODEL)%')]
        private readonly string $model,
        #[Autowire('%env(int:AI_TIMEOUT_SECONDS)%')]
        int $timeoutSeconds,
    ) {
        parent::__construct($httpClient, $parser, $timeoutSeconds);
    }

    public function name(): string
    {
        return 'groq';
    }

    public function isConfigured(): bool
    {
        return '' !== $this->apiKey;
    }

    protected function baseUrl(): string
    {
        return self::BASE_URL;
    }

    protected function apiKey(): string
    {
        return $this->apiKey;
    }

    protected function model(): string
    {
        return $this->model;
    }
}
