<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MetricsTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        /** @var string $storageDir */
        $storageDir = self::getContainer()->getParameter('app.storage_dir');
        (new \Symfony\Component\Filesystem\Filesystem())->remove($storageDir);
    }

    public function testMetricsAggregateStoredSubmissions(): void
    {
        $this->client->jsonRequest('POST', '/api/contact', [
            'name' => 'Recruiter',
            'email' => 'recruiter@example.com',
            'phone' => '+7 900 111-22-33',
            'comment' => 'У нас есть вакансия для вас, отличная зарплата!',
        ]);
        self::assertResponseStatusCodeSame(201);

        $this->client->jsonRequest('POST', '/api/contact', [
            'name' => 'Client',
            'email' => 'client@example.com',
            'phone' => '+7 900 444-55-66',
            'comment' => 'Нужно разработать сайт для магазина.',
        ]);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/metrics');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(2, $data['totalSubmissions']);
        self::assertSame(1, $data['byCategory']['job_offer']);
        self::assertSame(1, $data['byCategory']['project_inquiry']);
        self::assertSame(2, $data['byAnalysisSource']['heuristic']);
        self::assertNotNull($data['lastSubmissionAt']);
    }

    public function testMetricsAreEmptyWithoutSubmissions(): void
    {
        $this->client->request('GET', '/api/metrics');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(0, $data['totalSubmissions']);
        self::assertNull($data['lastSubmissionAt']);
    }
}
