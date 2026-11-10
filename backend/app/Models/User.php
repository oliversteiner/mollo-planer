<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserFunction;
use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Observers\UserObserver;
use App\Services\UserAvatar;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'job_function'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Same defaults as the database, so new instances never hold null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'mitglied',
        'notify_by_email' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'job_function' => UserFunction::class,
            'notify_by_email' => 'boolean',
        ];
    }

    /**
     * Projects this user is a member of, with the role (pivot attribute "membership").
     *
     * @return BelongsToMany<Project, $this, ProjectMember, 'membership'>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->using(ProjectMember::class)
            ->as('membership')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * Public URL of the avatar (500 x 500 WebP), null without one.
     * avatar_path is set only by App\Services\UserAvatar, never from input.
     *
     * @return Attribute<?string, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path === null
            ? null
            : Storage::disk(UserAvatar::DISK)->url($this->avatar_path));
    }

    /**
     * "Forgot password" (Fortify): queued mail with a link to the frontend,
     * in the language of the current request.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPasswordNotification($token))->locale(app()->getLocale()));
    }
}
