<?php

declare(strict_types=1);

return [
    'attempt_strategy' => env('GRADEBOOK_ATTEMPT_STRATEGY', 'latest_released'),
    'chunk_size' => (int) env('GRADEBOOK_COMPUTE_CHUNK_SIZE', 100),
];
