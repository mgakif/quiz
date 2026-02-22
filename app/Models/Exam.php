<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Exam extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'title',
        'class_id',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'class_id' => 'integer',
            'scheduled_at' => 'datetime',
        ];
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class, 'legacy_exam_id', 'id');
    }

    public function publicLinks(): HasMany
    {
        return $this->hasMany(PublicExamLink::class, 'exam_id');
    }
}
