<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RateLimitTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        // Lower the limit for this scenario only (resolved at runtime).
        $_ENV['RATE_LIMIT_CONTACT_LIMIT'] = '3';

        $this->client = self::createClient();

        /** @var CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get('rate_limiter.cache');
        $pool->clear();
    }

    protected function tearDown(): void
    {
        unset($_ENV['RATE_LIMIT_CONTACT_LIMIT']);

        parent::tearDown();
    }

    public function testExceedingTheLimitReturns429WithRetryAfter(): void
    {
        // The limiter counts every POST to /api/contact, valid or not,
        // so empty payloads are enough to exhaust the window.
        for ($i = 0; $i < 3; ++$i) {
            $this->client->jsonRequest('POST', '/api/contact', []);
            self::assertResponseStatusCodeSame(422);
        }

        $this->client->jsonRequest('POST', '/api/contact', []);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Too many requests. Please try again later.', $data['message']);
    }

    public function testOtherEndpointsAreNotRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('GET', '/api/health');
            self::assertResponseIsSuccessful();
        }
    }
}
