<?php

namespace App\Core\Accounts\Local;

/** Replaces the local database with a dump (Auftrag 45 C). A seam for the tests: they load a small fixture instead. */
interface DumpLoader
{
    /**
     * @throws \RuntimeException when the dump cannot be loaded
     */
    public function replaceWith(string $file): void;
}
