<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthTest extends WebTestCase
{
    public function testHealthEndpointReportsServiceState(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('ok', $data['status']);
        self::assertSame('ok', $data['checks']['storage']);
        self::assertArrayHasKey('mailer', $data['checks']);
        self::assertSame('auto', $data['checks']['ai']['mode']);
        self::assertSame('heuristic', $data['checks']['ai']['fallback']);
    }
}
