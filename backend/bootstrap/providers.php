<?php

use App\Core\Accounts\AccountsServiceProvider;
use App\Core\Identity\IdentityServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IdentityServiceProvider::class,
    AccountsServiceProvider::class,
];
