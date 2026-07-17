<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Exception\AiProviderException;
use App\Model\AiAnalysis;
use App\Model\ContactSubmission;
use App\Service\Ai\AiPrompt;
use App\Service\Ai\AiProviderInterface;
use App\Service\Ai\AiResponseParser;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Anthropic Claude via the Messages API.
 * API key: https://console.anthropic.com/.
 */
#[AutoconfigureTag(name: 'app.ai_provider', attributes: ['priority' => 70])]
final class AnthropicProvider implements AiProviderInterface
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiResponseParser $parser,
        #[Autowire('%env(ANTHROPIC_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(ANTHROPIC_MODEL)%')]
        private readonly string $model,
        #[Autowire('%env(int:AI_TIMEOUT_SECONDS)%')]
        private readonly int $timeoutSeconds,
    ) {
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function isConfigured(): bool
    {
        return '' !== $this->apiKey;
    }

    public function analyze(ContactSubmission $submission): AiAnalysis
    {
        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 1024,
                    'system' => AiPrompt::SYSTEM,
                    'messages' => [
                        ['role' => 'user', 'content' => AiPrompt::user($submission)],
                    ],
                ],
                'timeout' => $this->timeoutSeconds,
            ]);

            $data = $response->toArray();
        } catch (HttpClientException $exception) {
            throw new AiProviderException('Anthropic request failed: '.$exception->getMessage(), previous: $exception);
        }

        if ('refusal' === ($data['stop_reason'] ?? null)) {
            throw new AiProviderException('Anthropic declined to analyze the message.');
        }

        $text = null;
        foreach ($data['content'] ?? [] as $block) {
            if ('text' === ($block['type'] ?? null) && \is_string($block['text'] ?? null)) {
                $text = $block['text'];
                break;
            }
        }

        if (null === $text || '' === $text) {
            throw new AiProviderException('Anthropic returned an empty analysis.');
        }

        return $this->parser->parse($text, $this->name());
    }
}
