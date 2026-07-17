<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ContactApiTest extends WebTestCase
{
    private const VALID_PAYLOAD = [
        'name' => 'Ivan Petrov',
        'email' => 'ivan@example.com',
        'phone' => '+7 999 123-45-67',
        'comment' => 'I would like to discuss a backend project with you.',
    ];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        /** @var string $storageDir */
        $storageDir = self::getContainer()->getParameter('app.storage_dir');
        (new Filesystem())->remove($storageDir);
    }

    public function testValidSubmissionIsAccepted(): void
    {
        $this->client->jsonRequest('POST', '/api/contact', self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);
        $data = $this->decodeResponse();
        self::assertArrayHasKey('id', $data);
        self::assertSame('accepted', $data['status']);
        self::assertArrayHasKey('receivedAt', $data);
        // No AI keys in the test environment -> the heuristic fallback must kick in.
        self::assertSame('heuristic', $data['ai']['source']);
        self::assertArrayHasKey('sentiment', $data['ai']);
        self::assertArrayHasKey('category', $data['ai']);
    }

    public function testValidSubmissionIsPersistedToStorage(): void
    {
        $this->client->jsonRequest('POST', '/api/contact', self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);

        /** @var string $storageDir */
        $storageDir = self::getContainer()->getParameter('app.storage_dir');
        $file = $storageDir.'/submissions.jsonl';
        self::assertFileExists($file);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($file))));
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('ivan@example.com', $record['email']);
        self::assertSame('Ivan Petrov', $record['name']);
        self::assertNotEmpty($record['id']);
        self::assertNotEmpty($record['createdAt']);
        self::assertSame('heuristic', $record['ai']['source']);
    }

    public function testMissingRequiredFieldsReturn422WithErrorEnvelope(): void
    {
        $this->client->jsonRequest('POST', '/api/contact', []);

        self::assertResponseStatusCodeSame(422);
        $data = $this->decodeResponse();
        self::assertSame('Validation failed', $data['message']);
        foreach (['name', 'email', 'phone', 'comment'] as $field) {
            self::assertArrayHasKey($field, $data['errors'], \sprintf('Expected a validation error for "%s"', $field));
        }
    }

    public function testInvalidEmailReturns422(): void
    {
        $payload = array_merge(self::VALID_PAYLOAD, ['email' => 'not-an-email']);

        $this->client->jsonRequest('POST', '/api/contact', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('email', $this->decodeResponse()['errors']);
    }

    public function testWhitespaceOnlyNameReturns422(): void
    {
        $payload = array_merge(self::VALID_PAYLOAD, ['name' => '   ']);

        $this->client->jsonRequest('POST', '/api/contact', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('name', $this->decodeResponse()['errors']);
    }

    public function testTooLongCommentReturns422(): void
    {
        $payload = array_merge(self::VALID_PAYLOAD, ['comment' => str_repeat('a', 5001)]);

        $this->client->jsonRequest('POST', '/api/contact', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('comment', $this->decodeResponse()['errors']);
    }

    public function testMalformedJsonReturns400WithEnvelope(): void
    {
        $this->client->request(
            'POST',
            '/api/contact',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"name": broken',
        );

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('message', $this->decodeResponse());
    }

    public function testWrongHttpMethodReturns405WithEnvelope(): void
    {
        $this->client->request('GET', '/api/contact');

        self::assertResponseStatusCodeSame(405);
        self::assertSame('Method not allowed', $this->decodeResponse()['message']);
    }

    public function testUnknownApiRouteReturns404WithEnvelope(): void
    {
        $this->client->request('GET', '/api/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Resource not found', $this->decodeResponse()['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
