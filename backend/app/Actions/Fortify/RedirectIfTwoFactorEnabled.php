<?php

namespace App\Actions\Fortify;

use App\Support\AppSettings;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;

/**
 * Login step "ask for the 2FA code". Skipped when an admin has switched
 * two-factor authentication off (only possible outside production); the
 * next step of the login pipeline then checks the password.
 */
class RedirectIfTwoFactorEnabled extends RedirectIfTwoFactorAuthenticatable
{
    /**
     * @param  Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        if (! AppSettings::twoFactorEnabled()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
