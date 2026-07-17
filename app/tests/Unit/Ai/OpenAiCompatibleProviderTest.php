<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Dto\ContactRequest;
use App\Model\ContactSubmission;
use App\Model\Sentiment;
use App\Service\Ai\AiResponseParser;
use App\Service\Ai\Provider\GroqProvider;
use App\Service\Ai\Provider\OllamaProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenAiCompatibleProviderTest extends TestCase
{
    public function testGroqUsesOpenAiChatCompletionsProtocol(): void
    {
        $body = json_encode([
            'choices' => [[
                'message' => [
                    'content' => '{"sentiment":"negative","category":"other","spam_score":0.9,"reply_draft":null}',
                ],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($body): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.groq.com/openai/v1/chat/completions', $url);
            self::assertStringContainsString('Authorization: Bearer groq-key', implode("\n", $options['headers']));
            self::assertStringContainsString('"response_format"', (string) $options['body']);

            return new MockResponse($body);
        });

        $provider = new GroqProvider($client, new AiResponseParser(), 'groq-key', 'llama-test', 5);

        $analysis = $provider->analyze($this->submission());

        self::assertSame(Sentiment::Negative, $analysis->sentiment);
        self::assertSame('groq', $analysis->provider);
    }

    public function testOllamaNeedsNoKeyAndSkipsResponseFormat(): void
    {
        $body = json_encode([
            'choices' => [[
                'message' => ['content' => '{"sentiment":"neutral","category":"other","spam_score":0}'],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($body): MockResponse {
            self::assertSame('http://ollama.local:11434/v1/chat/completions', $url);
            self::assertStringNotContainsString('Authorization', implode("\n", $options['headers']));
            self::assertStringNotContainsString('"response_format"', (string) $options['body']);

            return new MockResponse($body);
        });

        $provider = new OllamaProvider($client, new AiResponseParser(), 'http://ollama.local:11434/v1', 'llama3.2', 5);

        self::assertTrue($provider->isConfigured());
        self::assertSame('ollama', $provider->analyze($this->submission())->provider);
    }

    public function testOllamaIsUnconfiguredWithoutBaseUrl(): void
    {
        $provider = new OllamaProvider(new MockHttpClient(), new AiResponseParser(), '', 'llama3.2', 5);

        self::assertFalse($provider->isConfigured());
    }

    private function submission(): ContactSubmission
    {
        return ContactSubmission::fromRequest(
            new ContactRequest('John', 'john@example.com', '+1 234 567', 'This is terrible, I am disappointed.'),
            '127.0.0.1',
        );
    }
}
