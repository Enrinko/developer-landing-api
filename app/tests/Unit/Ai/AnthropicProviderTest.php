<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Dto\ContactRequest;
use App\Exception\AiProviderException;
use App\Model\ContactSubmission;
use App\Model\MessageCategory;
use App\Service\Ai\AiResponseParser;
use App\Service\Ai\Provider\AnthropicProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AnthropicProviderTest extends TestCase
{
    public function testSendsProperRequestAndParsesAnswer(): void
    {
        $body = json_encode([
            'content' => [[
                'type' => 'text',
                'text' => "```json\n{\"sentiment\":\"neutral\",\"category\":\"question\",\"spam_score\":0.2}\n```",
            ]],
            'stop_reason' => 'end_turn',
        ], \JSON_THROW_ON_ERROR);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($body): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.anthropic.com/v1/messages', $url);
            $headers = implode("\n", $options['headers']);
            self::assertStringContainsString('x-api-key: secret-key', $headers);
            self::assertStringContainsString('anthropic-version: 2023-06-01', $headers);

            return new MockResponse($body);
        });

        $provider = new AnthropicProvider($client, new AiResponseParser(), 'secret-key', 'claude-opus-4-8', 5);

        $analysis = $provider->analyze($this->submission());

        self::assertSame(MessageCategory::Question, $analysis->category);
        self::assertSame('anthropic', $analysis->provider);
    }

    public function testRefusalStopReasonThrows(): void
    {
        $body = json_encode(['content' => [], 'stop_reason' => 'refusal'], \JSON_THROW_ON_ERROR);
        $client = new MockHttpClient(new MockResponse($body));
        $provider = new AnthropicProvider($client, new AiResponseParser(), 'secret-key', 'claude-opus-4-8', 5);

        $this->expectException(AiProviderException::class);

        $provider->analyze($this->submission());
    }

    private function submission(): ContactSubmission
    {
        return ContactSubmission::fromRequest(
            new ContactRequest('John', 'john@example.com', '+1 234 567', 'How much does a backend cost?'),
            '127.0.0.1',
        );
    }
}
