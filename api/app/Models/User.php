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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property UserRole $role
 * @property UserStatus $status
 * @property string $timezone
 * @property string $locale
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $last_active_at
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $sessions_valid_after
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ActivityLog> $activityLogs
 * @property-read int|null $activity_logs_count
 * @property-read Collection<int, Certificate> $certificates
 * @property-read int|null $certificates_count
 * @property-read Collection<int, LevelEnrollment> $levelEnrollments
 * @property-read int|null $level_enrollments_count
 * @property-read Collection<int, LevelTestAttempt> $levelTestAttempts
 * @property-read int|null $level_test_attempts_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read OnboardingPreference|null $onboarding
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, ProgrammeEnrollment> $programmeEnrollments
 * @property-read int|null $programme_enrollments_count
 * @property-read Collection<int, PushSubscription> $pushSubscriptions
 * @property-read int|null $push_subscriptions_count
 * @property-read Collection<int, StudentQuestionProgress> $questionProgress
 * @property-read int|null $question_progress_count
 * @property-read Collection<int, QuizAttempt> $quizAttempts
 * @property-read int|null $quiz_attempts_count
 * @property-read Collection<int, RefreshToken> $refreshTokens
 * @property-read int|null $refresh_tokens_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, StudentStreak> $streaks
 * @property-read int|null $streaks_count
 * @property-read Collection<int, Permission> $teams
 * @property-read int|null $teams_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User admins()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User students()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFailedLoginAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastActiveAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLockedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOnboardingCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSessionsValidAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
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

    /** @return HasMany<LevelEnrollment, $this> */
    public function levelEnrollments(): HasMany
    {
        return $this->hasMany(LevelEnrollment::class);
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** @return HasMany<StudentQuestionProgress, $this> */
    public function questionProgress(): HasMany
    {
        return $this->hasMany(StudentQuestionProgress::class);
    }

    /** @return HasMany<LevelTestAttempt, $this> */
    public function levelTestAttempts(): HasMany
    {
        return $this->hasMany(LevelTestAttempt::class);
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<StudentStreak, $this> */
    public function streaks(): HasMany
    {
        return $this->hasMany(StudentStreak::class);
    }

    /** @return HasMany<Certificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /** @return HasMany<RefreshToken, $this> */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /** @return HasMany<ActivityLog, $this> */
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
