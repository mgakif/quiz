<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_item_id',
        'scores',
        'total_points',
        'graded_by',
        'graded_at',
        'override_reason',
        'is_draft',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'total_points' => 'decimal:2',
            'graded_at' => 'datetime',
            'is_draft' => 'boolean',
        ];
    }

    public function attemptItem(): BelongsTo
    {
        return $this->belongsTo(AttemptItem::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
