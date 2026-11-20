<?php

namespace App\Models;

use App\Enums\Locale;
use App\Enums\UserActivity;
use App\Models\Concerns\HasRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyRegistrationEmail;
use App\Observers\UserObserver;
use App\Services\UserAvatar;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
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

#[Fillable(['name', 'email', 'password', 'role', 'activity'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements MustVerifyEmailContract, PasskeyUser
{
    use HasApiTokens, HasRole, MustVerifyEmail, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Same defaults as the database, so new instances never hold null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notify_by_email' => true,
        'is_agent' => false,
    ];

    protected static function roleScope(): string
    {
        return Role::SCOPE_SYSTEM;
    }

    protected static function booted(): void
    {
        // Somebody created without a role gets the default role of the settings.
        static::creating(function (self $user): void {
            $user->role_id ??= Role::defaultOf(Role::SCOPE_SYSTEM)?->id;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array{email_verified_at: 'datetime', approved_at: 'datetime', is_agent: 'boolean', password: 'hashed', two_factor_confirmed_at: 'datetime', activity: class-string<UserActivity>, notify_by_email: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_agent' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'activity' => UserActivity::class,
            'notify_by_email' => 'boolean',
        ];
    }

    /**
     * An agent account (Claude, a person's assistant): no login in the browser, access only by a token for the
     * MCP connector (app:user-token).
     */
    public function isAgent(): bool
    {
        return (bool) $this->getAttribute('is_agent');
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
            ->withPivot('id', 'role_id')
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
     * The language of a mail about a change the person made in their own request (a security notice):
     * the stored language of the account (users.locale), otherwise the one of the current request,
     * otherwise the application's.
     */
    public function mailLocale(): string
    {
        return Locale::tryFrom((string) $this->getAttribute('locale'))->value ?? app()->getLocale();
    }

    /**
     * The language of a mail that arises in somebody else's request (an admin resets the factor, releases or
     * rejects the account): the stored language of the account (users.locale), otherwise the application's
     * (APP_LOCALE, kept in app.default_locale: app.locale changes with every request). Never the language of the admin's request.
     */
    public function accountLocale(): string
    {
        return Locale::tryFrom((string) $this->getAttribute('locale'))->value ?? (string) config('app.default_locale');
    }

    /**
     * "Forgot password" (Fortify): queued mail with a link to the frontend,
     * in the language of the current request.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        // An agent account has no web access: nothing to reset, and no mail to an address nobody reads for it.
        if ($this->isAgent()) {
            return;
        }

        $this->notify((new ResetPasswordNotification($token))->locale(app()->getLocale()));
    }

    /**
     * Exposed to GraphQL as User.approved.
     *
     * @return Attribute<bool, never>
     */
    protected function approved(): Attribute
    {
        return Attribute::get(fn (): bool => $this->isApproved());
    }

    /**
     * Exposed to GraphQL as User.emailVerified.
     *
     * @return Attribute<bool, never>
     */
    protected function emailVerified(): Attribute
    {
        return Attribute::get(fn (): bool => $this->hasVerifiedEmail());
    }

    /**
     * Whether an admin has released the account (self-registration waits for it).
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Verification mail after self-registration (Fortify), queued, in the
     * language of the current request.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyRegistrationEmail)->locale(app()->getLocale()));
    }
}
