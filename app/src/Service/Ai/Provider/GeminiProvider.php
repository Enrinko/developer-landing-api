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
 * Google Gemini via the generateContent REST API (v1beta).
 * Free API key: https://aistudio.google.com/apikey.
 */
#[AutoconfigureTag(name: 'app.ai_provider', attributes: ['priority' => 100])]
final class GeminiProvider implements AiProviderInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiResponseParser $parser,
        #[Autowire('%env(GEMINI_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(GEMINI_MODEL)%')]
        private readonly string $model,
        #[Autowire('%env(int:AI_TIMEOUT_SECONDS)%')]
        private readonly int $timeoutSeconds,
    ) {
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return '' !== $this->apiKey;
    }

    public function analyze(ContactSubmission $submission): AiAnalysis
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                \sprintf('%s/models/%s:generateContent', self::BASE_URL, $this->model),
                [
                    'headers' => ['x-goog-api-key' => $this->apiKey],
                    'json' => [
                        'system_instruction' => ['parts' => [['text' => AiPrompt::SYSTEM]]],
                        'contents' => [['parts' => [['text' => AiPrompt::user($submission)]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ],
                    'timeout' => $this->timeoutSeconds,
                ],
            );

            $data = $response->toArray();
        } catch (HttpClientException $exception) {
            throw new AiProviderException('Gemini request failed: '.$exception->getMessage(), previous: $exception);
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!\is_string($text) || '' === $text) {
            throw new AiProviderException('Gemini returned an empty analysis.');
        }

        return $this->parser->parse($text, $this->name());
    }
}
