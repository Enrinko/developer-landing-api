<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Exception\AiProviderException;
use App\Model\AiAnalysis;
use App\Model\ContactSubmission;
use App\Service\Ai\AiPrompt;
use App\Service\Ai\AiProviderInterface;
use App\Service\Ai\AiResponseParser;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Shared implementation for every OpenAI-compatible chat completions API:
 * OpenAI itself, Groq and a local Ollama all speak the same protocol,
 * so three providers cost one HTTP integration.
 */
abstract class OpenAiCompatibleProvider implements AiProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiResponseParser $parser,
        private readonly int $timeoutSeconds,
    ) {
    }

    abstract protected function baseUrl(): string;

    abstract protected function apiKey(): string;

    abstract protected function model(): string;

    /**
     * Whether the backend supports response_format: json_object
     * (OpenAI and Groq do; Ollama is prompt-guided only).
     */
    protected function supportsJsonResponseFormat(): bool
    {
        return true;
    }

    public function analyze(ContactSubmission $submission): AiAnalysis
    {
        $payload = [
            'model' => $this->model(),
            'messages' => [
                ['role' => 'system', 'content' => AiPrompt::SYSTEM],
                ['role' => 'user', 'content' => AiPrompt::user($submission)],
            ],
        ];

        if ($this->supportsJsonResponseFormat()) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $headers = [];
        if ('' !== $this->apiKey()) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey();
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                rtrim($this->baseUrl(), '/').'/chat/completions',
                [
                    'headers' => $headers,
                    'json' => $payload,
                    'timeout' => $this->timeoutSeconds,
                ],
            );

            $data = $response->toArray();
        } catch (HttpClientException $exception) {
            throw new AiProviderException(\sprintf('%s request failed: %s', $this->name(), $exception->getMessage()), previous: $exception);
        }

        $text = $data['choices'][0]['message']['content'] ?? null;
        if (!\is_string($text) || '' === $text) {
            throw new AiProviderException(\sprintf('%s returned an empty analysis.', $this->name()));
        }

        return $this->parser->parse($text, $this->name());
    }
}
