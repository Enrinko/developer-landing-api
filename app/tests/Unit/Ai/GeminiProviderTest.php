<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Dto\ContactRequest;
use App\Exception\AiProviderException;
use App\Model\ContactSubmission;
use App\Model\Sentiment;
use App\Service\Ai\AiResponseParser;
use App\Service\Ai\Provider\GeminiProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeminiProviderTest extends TestCase
{
    public function testSendsProperRequestAndParsesAnswer(): void
    {
        $body = json_encode([
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => '{"sentiment":"positive","category":"project_inquiry","spam_score":0.05,"reply_draft":"Thanks!"}',
                ]]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($body): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringStartsWith(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent',
                $url,
            );
            self::assertStringContainsString('x-goog-api-key: secret-key', implode("\n", $options['headers']));
            self::assertStringContainsString('responseMimeType', (string) $options['body']);

            return new MockResponse($body);
        });

        $provider = new GeminiProvider($client, new AiResponseParser(), 'secret-key', 'gemini-test', 5);

        $analysis = $provider->analyze($this->submission());

        self::assertSame(Sentiment::Positive, $analysis->sentiment);
        self::assertSame('gemini', $analysis->provider);
    }

    public function testSkipsThoughtPartsFromThinkingModels(): void
    {
        $body = json_encode([
            'candidates' => [[
                'content' => ['parts' => [
                    ['text' => 'Let me reason about this message...', 'thought' => true],
                    ['text' => '{"sentiment":"positive","category":"question","spam_score":0.1}',
                        'thoughtSignature' => 'abc'],
                ]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $provider = new GeminiProvider(
            new MockHttpClient(new MockResponse($body)),
            new AiResponseParser(),
            'secret-key',
            'gemini-test',
            5,
        );

        $analysis = $provider->analyze($this->submission());

        self::assertSame(Sentiment::Positive, $analysis->sentiment);
    }

    public function testHttpErrorIsWrappedIntoProviderException(): void
    {
        $client = new MockHttpClient(new MockResponse('upstream broken', ['http_code' => 500]));
        $provider = new GeminiProvider($client, new AiResponseParser(), 'secret-key', 'gemini-test', 5);

        $this->expectException(AiProviderException::class);

        $provider->analyze($this->submission());
    }

    public function testIsConfiguredRequiresApiKey(): void
    {
        $provider = new GeminiProvider(new MockHttpClient(), new AiResponseParser(), '', 'gemini-test', 5);

        self::assertFalse($provider->isConfigured());
    }

    private function submission(): ContactSubmission
    {
        return ContactSubmission::fromRequest(
            new ContactRequest('John', 'john@example.com', '+1 234 567', 'Build me a backend, please.'),
            '127.0.0.1',
        );
    }
}
