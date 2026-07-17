<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A single AI provider failed (network, HTTP error, malformed response).
 * The chain catches this and moves on to the next provider.
 */
final class AiProviderException extends \RuntimeException
{
}
