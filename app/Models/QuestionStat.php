<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'question_version_id',
        'usage_count',
        'correct_count',
        'incorrect_count',
        'correct_rate',
        'avg_score',
        'appeal_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'correct_rate' => 'decimal:2',
            'avg_score' => 'decimal:2',
            'last_used_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class);
    }
}
