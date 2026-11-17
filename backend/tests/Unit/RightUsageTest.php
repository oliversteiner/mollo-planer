<?php

namespace Tests\Unit;

use App\Enums\Right;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The list of rights is closed (docs/rollen-funktionen-rechte.md, §4): every
 * right is checked somewhere in the app, and has a short text in both
 * languages. That every check uses a right of the list is guaranteed by the
 * type: Rights::allows takes only the enum.
 */
class RightUsageTest extends TestCase
{
    /**
     * Rights whose feature is built in a later step of docs/umsetzung.md.
     * When the step uses the right, its line here must go.
     *
     * @var array<string, string>
     */
    private const PENDING = [
        'planer.import' => 'I2',
        'aiContext.update' => 'B6',
        'assistant.use' => 'B5a',
        'ideas.create' => 'B6',
        'ideas.viewAny' => 'B6',
        'system.view' => 'system-plan.md',
        'database.view' => 'system-plan.md',
    ];

    public function test_every_right_is_checked_somewhere_or_is_pending(): void
    {
        $used = $this->rightsUsedInApp();

        foreach (Right::cases() as $right) {
            $isUsed = in_array($right->name, $used, true);
            $isPending = array_key_exists($right->value, self::PENDING);

            $this->assertTrue($isUsed || $isPending, "{$right->value} is checked nowhere and is not pending.");
            $this->assertFalse($isUsed && $isPending, "{$right->value} is checked now, remove it from PENDING.");
        }
    }

    public function test_pending_rights_are_real_rights(): void
    {
        foreach (array_keys(self::PENDING) as $value) {
            $this->assertNotNull(Right::tryFrom($value), "{$value} is no right.");
        }
    }

    public function test_every_right_has_a_short_text_in_both_languages(): void
    {
        foreach (Right::cases() as $right) {
            foreach (['de', 'en'] as $locale) {
                $label = $right->label($locale);

                $this->assertNotSame('rights.'.$right->value, $label, "{$right->value} has no text in {$locale}.");
                $this->assertNotSame('', $label);
            }
        }
    }

    public function test_every_right_has_exactly_one_scope(): void
    {
        $system = Right::ofScope(Right::SCOPE_SYSTEM);
        $project = Right::ofScope(Right::SCOPE_PROJECT);

        $this->assertCount(count(Right::cases()), [...$system, ...$project]);
        $this->assertSame([], array_uintersect($system, $project, fn (Right $a, Right $b): int => $a->value <=> $b->value));
    }

    /**
     * Case names used as Right::NAME in app/, outside the enum itself.
     *
     * @return list<string>
     */
    private function rightsUsedInApp(): array
    {
        $names = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php')->notPath('Enums/Right.php') as $file) {
            preg_match_all('/\bRight::([A-Z_]+)\b/', $file->getContents(), $matches);
            $names = [...$names, ...$matches[1]];
        }

        return array_values(array_unique(array_diff($names, ['SCOPE_PROJECT', 'SCOPE_SYSTEM'])));
    }
}
