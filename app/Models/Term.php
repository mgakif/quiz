<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Term extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'start_date',
        'end_date',
        'is_active',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $term): void {
            if (blank($term->id)) {
                $term->id = (string) Str::uuid();
            }
        });
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function studentGrades(): HasMany
    {
        return $this->hasMany(StudentTermGrade::class);
    }
}
