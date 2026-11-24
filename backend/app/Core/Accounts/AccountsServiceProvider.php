<?php

namespace App\Core\Accounts;

use App\Core\Accounts\Console\AccountsRestore;
use App\Core\Accounts\Console\AccountsSave;
use App\Core\Accounts\Console\LocalLoadDump;
use App\Core\Accounts\Local\DumpLoader;
use App\Core\Accounts\Local\KeepLocalAccounts;
use App\Core\Accounts\Local\MariaDbDumpLoader;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Accounts of the base (starter kit): the local snapshots and their commands (Auftrag 45). */
class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KeepLocalAccounts::class);
        $this->app->bind(DumpLoader::class, MariaDbDumpLoader::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([AccountsSave::class, AccountsRestore::class, LocalLoadDump::class]);

        Event::listen(CommandStarting::class, fn (CommandStarting $event) => $this->app->make(KeepLocalAccounts::class)->starting($event));
        Event::listen(CommandFinished::class, fn (CommandFinished $event) => $this->app->make(KeepLocalAccounts::class)->finished($event));
    }
}
