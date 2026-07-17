<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Ai\AiProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Collects the service health snapshot: storage writability, mailer
 * configuration and which AI providers are ready to be used.
 */
final class HealthReporter
{
    /**
     * @param iterable<AiProviderInterface> $providers
     */
    public function __construct(
        #[Autowire('%app.storage_dir%')]
        private readonly string $storageDir,
        #[AutowireIterator('app.ai_provider')]
        private readonly iterable $providers,
        #[Autowire('%env(AI_PROVIDER)%')]
        private readonly string $aiMode,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
    ) {
    }

    /**
     * @return array{status: string, time: string, checks: array<string, mixed>}
     */
    public function report(): array
    {
        $storageOk = $this->isStorageWritable();

        $configuredProviders = [];
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                $configuredProviders[] = $provider->name();
            }
        }

        return [
            'status' => $storageOk ? 'ok' : 'fail',
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'checks' => [
                'storage' => $storageOk ? 'ok' : 'unavailable',
                'mailer' => str_starts_with($this->mailerDsn, 'null') ? 'not_configured' : 'configured',
                'ai' => [
                    'mode' => $this->aiMode,
                    'configuredProviders' => $configuredProviders,
                    'fallback' => 'heuristic',
                ],
            ],
        ];
    }

    private function isStorageWritable(): bool
    {
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            return false;
        }

        return is_writable($this->storageDir);
    }
}
