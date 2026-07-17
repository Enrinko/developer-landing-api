<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ContactEmailTest extends WebTestCase
{
    private const VALID_PAYLOAD = [
        'name' => 'Anna Smirnova',
        'email' => 'anna@example.com',
        'phone' => '+7 912 345-67-89',
        'comment' => 'Please contact me about a Symfony backend project.',
    ];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        /** @var string $storageDir */
        $storageDir = self::getContainer()->getParameter('app.storage_dir');
        (new Filesystem())->remove($storageDir);
    }

    public function testSubmissionSendsOwnerNotificationAndUserCopy(): void
    {
        $this->client->jsonRequest('POST', '/api/contact', self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailCount(2);

        $ownerEmail = self::getMailerMessage(0);
        self::assertNotNull($ownerEmail);
        self::assertEmailAddressContains($ownerEmail, 'To', 'owner@example.com');
        self::assertEmailAddressContains($ownerEmail, 'Reply-To', 'anna@example.com');
        self::assertEmailHtmlBodyContains($ownerEmail, 'Please contact me about a Symfony backend project.');
        self::assertEmailHtmlBodyContains($ownerEmail, 'Anna Smirnova');
        self::assertEmailHtmlBodyContains($ownerEmail, 'AI-анализ');

        $userEmail = self::getMailerMessage(1);
        self::assertNotNull($userEmail);
        self::assertEmailAddressContains($userEmail, 'To', 'anna@example.com');
        self::assertEmailHtmlBodyContains($userEmail, 'Anna Smirnova');
    }

    public function testSuccessfulSubmissionReportsEmailsSent(): void
    {
        $this->client->jsonRequest('POST', '/api/contact', self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('sent', $data['emails']);
    }

    public function testInvalidSubmissionSendsNoEmails(): void
    {
        $this->client->jsonRequest('POST', '/api/contact', ['name' => 'X']);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }
}
