<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttemptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id',
        'question_version_id',
        'order',
        'max_points',
    ];

    protected function casts(): array
    {
        return [
            'max_points' => 'decimal:2',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class);
    }

    public function response(): HasOne
    {
        return $this->hasOne(AttemptResponse::class, 'attempt_item_id');
    }

    public function rubricScore(): HasOne
    {
        return $this->hasOne(RubricScore::class);
    }

    public function aiGrading(): HasOne
    {
        return $this->hasOne(AiGrading::class);
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(Appeal::class);
    }
}
