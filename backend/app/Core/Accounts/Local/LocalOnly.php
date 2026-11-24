<?php

namespace App\Core\Accounts\Local;

use RuntimeException;

/**
 * Everything of Auftrag 45 works on the developer's own machine only: APP_ENV=local and a database on this machine
 * (SQLite, a socket, 127.0.0.1, localhost or ::1). TEST and PROD are guarded by Auftrag 37 and never reach this code.
 */
final class LocalOnly
{
    private const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /** Null when allowed, otherwise why not. */
    public static function refusal(): ?string
    {
        if (! app()->environment('local')) {
            return __('console.local_accounts.not_local', ['env' => app()->environment()]);
        }

        $connection = (array) config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? '') === 'sqlite') {
            return null;
        }

        if ((string) ($connection['unix_socket'] ?? '') !== '') {
            return null;
        }

        $host = (string) ($connection['host'] ?? '');

        return in_array($host, self::LOCAL_HOSTS, true) ? null : __('console.local_accounts.not_local_host', ['host' => $host]);
    }

    /**
     * @throws RuntimeException
     */
    public static function assert(): void
    {
        $refusal = self::refusal();

        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }
    }
}
