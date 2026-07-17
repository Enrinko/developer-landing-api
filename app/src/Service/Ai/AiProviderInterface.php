<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Exception\AiProviderException;
use App\Model\AiAnalysis;
use App\Model\ContactSubmission;

/**
 * One concrete AI backend (Strategy). Implementations are tagged with
 * `app.ai_provider` and tried by ChainAiAnalyzer in priority order.
 */
interface AiProviderInterface
{
    public function name(): string;

    /**
     * Whether the provider has everything it needs (API key / base URL) to be called.
     */
    public function isConfigured(): bool;

    /**
     * @throws AiProviderException on any provider failure
     */
    public function analyze(ContactSubmission $submission): AiAnalysis;
}
