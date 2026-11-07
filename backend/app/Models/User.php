<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Same defaults as the database, so new instances never hold null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
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
            'is_admin' => 'boolean',
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
}
