<?php

namespace Tests\Unit;

use App\Core\Pdf\PageLayout;
use PHPUnit\Framework\TestCase;

/**
 * The defaults of the layout and the values of the letterhead guide (docs/briefpapier/values.json, read by
 * briefpapier-anleitung.py) are the same (Auftrag 39 F).
 */
class PageLayoutDefaultsTest extends TestCase
{
    public function test_the_defaults_are_the_values_of_the_guide(): void
    {
        $values = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/docs/briefpapier/values.json'), true, 512, JSON_THROW_ON_ERROR);
        unset($values['comment']);

        $this->assertEquals(PageLayout::DEFAULTS, $values);
        $this->assertEquals((new PageLayout)->toArray(), array_map('floatval', $values));
    }
}
