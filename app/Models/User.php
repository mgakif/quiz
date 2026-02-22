<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STUDENT = 'student';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'student_id');
    }

    public function studentTermGrades(): HasMany
    {
        return $this->hasMany(StudentTermGrade::class, 'student_id');
    }

    public function publicExamLinks(): HasMany
    {
        return $this->hasMany(PublicExamLink::class, 'created_by');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isTeacher();
    }
}
