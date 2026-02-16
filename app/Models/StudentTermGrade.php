<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentTermGrade extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'term_id',
        'student_id',
        'computed_grade',
        'overridden_grade',
        'override_reason',
        'computed_at',
        'overridden_at',
    ];

    protected function casts(): array
    {
        return [
            'computed_grade' => 'decimal:2',
            'overridden_grade' => 'decimal:2',
            'computed_at' => 'datetime',
            'overridden_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $studentTermGrade): void {
            if (blank($studentTermGrade->id)) {
                $studentTermGrade->id = (string) Str::uuid();
            }
        });
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function finalGrade(): ?float
    {
        if ($this->overridden_grade !== null) {
            return round((float) $this->overridden_grade, 2);
        }

        if ($this->computed_grade !== null) {
            return round((float) $this->computed_grade, 2);
        }

        return null;
    }
}
