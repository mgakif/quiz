<?php

declare(strict_types=1);

namespace App\Services\AI;

use RuntimeException;

class AIClient
{
    public function complete(string $prompt): string
    {
        throw new RuntimeException('AIClient is not implemented yet.');
    }
}
