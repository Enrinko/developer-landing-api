<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Service\Ai\AiResponseParser;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI chat completions. API key: https://platform.openai.com/api-keys.
 */
#[AutoconfigureTag(name: 'app.ai_provider', attributes: ['priority' => 80])]
final class OpenAiProvider extends OpenAiCompatibleProvider
{
    public function __construct(
        HttpClientInterface $httpClient,
        AiResponseParser $parser,
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(OPENAI_MODEL)%')]
        private readonly string $model,
        #[Autowire('%env(OPENAI_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(int:AI_TIMEOUT_SECONDS)%')]
        int $timeoutSeconds,
    ) {
        parent::__construct($httpClient, $parser, $timeoutSeconds);
    }

    public function name(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return '' !== $this->apiKey;
    }

    protected function baseUrl(): string
    {
        return $this->baseUrl;
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
