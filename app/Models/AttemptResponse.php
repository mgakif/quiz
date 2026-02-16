<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptResponse extends Model
{
    use HasFactory;

    protected $table = 'responses';

    protected $fillable = [
        'attempt_item_id',
        'response_payload',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function attemptItem(): BelongsTo
    {
        return $this->belongsTo(AttemptItem::class);
    }
}
