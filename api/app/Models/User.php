<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Concerns\HasUuid;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'timezone',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'onboarding_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
            'locked_until' => 'datetime',
            'sessions_valid_after' => 'datetime',
            'failed_login_attempts' => 'integer',
        ];
    }

    // ------------------------------------------------------------ relations --

    /** @return HasOne<OnboardingPreference, $this> */
    public function onboarding(): HasOne
    {
        return $this->hasOne(OnboardingPreference::class);
    }

    /** @return HasMany<ProgrammeEnrollment, $this> */
    public function programmeEnrollments(): HasMany
    {
        return $this->hasMany(ProgrammeEnrollment::class);
    }

    public function levelEnrollments(): HasMany
    {
        return $this->hasMany(LevelEnrollment::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function questionProgress(): HasMany
    {
        return $this->hasMany(StudentQuestionProgress::class);
    }

    public function levelTestAttempts(): HasMany
    {
        return $this->hasMany(LevelTestAttempt::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function streaks(): HasMany
    {
        return $this->hasMany(StudentStreak::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // -------------------------------------------------------------- helpers --

    /** Queued so sign-up and password reset never wait on SMTP. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailQueued);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordQueued($token));
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Abilities minted onto the Sanctum access token. Deliberately coarse: the
     * DB-backed `role` column is re-checked by EnsureRole on every request, so
     * revoking a role takes effect immediately without waiting for expiry.
     *
     * @return array<int, string>
     */
    public function tokenAbilities(): array
    {
        return [$this->role->value];
    }

    // --------------------------------------------------------------- scopes --

    public function scopeStudents($query)
    {
        return $query->where('role', UserRole::Student->value);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', UserRole::Admin->value);
    }

    public function scopeActive($query)
    {
        return $query->where('status', UserStatus::Active->value);
    }
}
