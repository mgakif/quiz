<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GuestUser extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'display_name',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $guestUser): void {
            if (blank($guestUser->id)) {
                $guestUser->id = (string) Str::uuid();
            }
        });
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class, 'guest_id');
    }
}
