<?php

namespace App\Core\Accounts\Local;

use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Loads a dump of mysqldump/mariadb-dump (.sql or .sql.gz, as ~/www/_backups/hol-sicherungen.sh fetches them) into the
 * local MariaDB: drops all tables, then pipes the dump into the client. The password goes in an options file (0600,
 * deleted afterwards), never on the command line where other processes could read it.
 */
final class MariaDbDumpLoader implements DumpLoader
{
    public function replaceWith(string $file): void
    {
        LocalOnly::assert();

        if (! is_file($file)) {
            throw new RuntimeException(__('console.local_accounts.no_file', ['file' => $file]));
        }

        $connection = (array) config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? '', ['mariadb', 'mysql'], true)) {
            throw new RuntimeException(__('console.local_accounts.dump_driver', ['driver' => (string) ($connection['driver'] ?? '')]));
        }

        $options = tempnam(sys_get_temp_dir(), 'planer-db-');
        chmod($options, 0600);
        file_put_contents($options, implode("\n", array_filter([
            '[client]',
            'host='.($connection['host'] ?? '127.0.0.1'),
            'port='.($connection['port'] ?? '3306'),
            'user='.($connection['username'] ?? ''),
            'password='.($connection['password'] ?? ''),
            ($connection['unix_socket'] ?? '') !== '' ? 'socket='.$connection['unix_socket'] : null,
        ]))."\n");

        try {
            Schema::dropAllTables();

            $reader = str_ends_with($file, '.gz') ? 'gzip -dc "$1"' : 'cat "$1"';
            $process = new Process(['bash', '-o', 'pipefail', '-c', $reader.' | "$2" --defaults-extra-file="$3" "$4"', 'load', $file, (string) config('local_accounts.client'), $options, (string) ($connection['database'] ?? '')]);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(__('console.local_accounts.dump_failed', ['reason' => trim($process->getErrorOutput()) ?: (string) $process->getExitCode()]));
            }
        } finally {
            @unlink($options);
        }
    }
}
