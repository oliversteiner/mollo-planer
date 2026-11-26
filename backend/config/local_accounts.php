<?php

$home = rtrim((string) (getenv('HOME') ?: ''), '/');

// A leading "~" in .env means the home folder (.env does not expand it).
$path = fn (string $value): string => str_starts_with($value, '~/') ? $home.substr($value, 1) : $value;

/*
 * Accounts that survive a rebuild of the LOCAL database (Auftrag 45, docs/lokale-konten.md). Only for APP_ENV=local
 * with a database on this machine; TEST and PROD are guarded by Auftrag 37. The snapshots hold password hashes, 2FA
 * secrets and token hashes: they live outside the repository and outside storage/, and are never passed on.
 */
return [
    // Save before and restore after migrate:fresh, migrate:refresh, migrate:reset, db:wipe. Never in "testing".
    'keep' => (bool) env('LOCAL_ACCOUNTS_KEEP', true),

    // Where the snapshots go (folder 0700, files 0600). Never inside the repository or storage/.
    'dir' => $path((string) env('LOCAL_ACCOUNTS_BACKUP_DIR', $home.'/www/_backups/planer/local')),

    // How many snapshots stay; older ones are deleted.
    'retain' => 20,

    // app:local-load-dump: where the dumps of TEST are (~/www/_backups/hol-sicherungen.sh) and the client that loads them.
    'test_dumps' => $path((string) env('LOCAL_TEST_DUMPS_DIR', $home.'/www/_backups/planer/test/db')),
    'client' => env('LOCAL_MARIADB_CLIENT', 'mariadb'),
];
