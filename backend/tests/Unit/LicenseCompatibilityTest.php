<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The planer is to be licensed under AGPL-3.0-or-later (Auftrag 81, docs/lizenz-analyse.md). GPL-2.0-only fits neither
 * AGPL-3.0 nor the Apache-2.0 packages of the backend, so mPDF went (81 B) and nothing like it may come back unnoticed.
 */
class LicenseCompatibilityTest extends TestCase
{
    /** Licenses that do not fit AGPL-3.0 together with Apache-2.0 (SPDX, also the old spelling without suffix). */
    private const INCOMPATIBLE = ['GPL-2.0-only', 'GPL-2.0', 'LGPL-2.0-only', 'LGPL-2.0'];

    public function test_no_package_for_production_is_only_under_an_incompatible_license(): void
    {
        $lock = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
        $refused = [];

        foreach ($lock['packages'] as $package) {
            $licenses = $package['license'] ?? [];

            // Several licenses are a choice (nette: BSD-3-Clause, GPL-2.0-only, GPL-3.0-only): one that fits is enough.
            if ($licenses !== [] && array_diff($licenses, self::INCOMPATIBLE) === []) {
                $refused[] = $package['name'].' ('.implode(', ', $licenses).')';
            }
        }

        $this->assertSame([], $refused, 'Packages only under a license that does not fit AGPL-3.0 (docs/lizenz-analyse.md).');
    }

    public function test_mpdf_is_gone(): void
    {
        $lock = (string) file_get_contents(dirname(__DIR__, 2).'/composer.lock');
        $this->assertStringNotContainsString('"name": "mpdf/mpdf"', $lock);

        $users = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app')) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && preg_match('/\bMpdf\\\\/', (string) file_get_contents($file->getPathname())) === 1) {
                $users[] = $file->getFilename();
            }
        }

        $this->assertSame([], $users, 'No code may use mPDF (GPL-2.0-only); the PDF frame is App\Core\Pdf\PdfFrame.');
    }
}
