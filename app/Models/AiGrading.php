<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGrading extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_item_id',
        'response_json',
        'confidence',
        'flags',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'flags' => 'array',
            'confidence' => 'decimal:4',
        ];
    }

    public function attemptItem(): BelongsTo
    {
        return $this->belongsTo(AttemptItem::class);
    }
}
