<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Model\AiAnalysis;
use App\Model\ContactSubmission;

/**
 * What the application layer consumes. Implementations must NEVER throw:
 * if no AI provider is reachable, a heuristic result is returned instead
 * (graceful fallback — the service keeps working without AI).
 */
interface AiAnalyzerInterface
{
    public function analyze(ContactSubmission $submission): AiAnalysis;
}
