<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leaderboard extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'class_id',
        'period',
        'start_date',
        'end_date',
        'computed_at',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'computed_at' => 'datetime',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
