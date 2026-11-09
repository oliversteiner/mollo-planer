<?php

namespace App\Support;

use App\Enums\LabelColor;
use App\Models\Label;
use App\Models\LabelSet;
use Illuminate\Support\Facades\DB;

/**
 * The label sets that come with the app. They cannot be changed; to adapt
 * one, it is duplicated. Names, descriptions and categories are
 * translated (lang/de|en/labels.php, keyed by the set and label key); the
 * stored German name is the fallback and keeps names unique per set.
 */
final class BuiltinLabelSets
{
    /**
     * @var array<string, list<array{key: string, name: string, category: string, color: LabelColor}>>
     */
    public const SETS = [
        'software_dev' => [
            ['key' => 'research', 'name' => 'Recherche', 'category' => 'phase', 'color' => LabelColor::BLUE],
            ['key' => 'design', 'name' => 'Design', 'category' => 'phase', 'color' => LabelColor::PINK],
            ['key' => 'feature', 'name' => 'Feature', 'category' => 'type', 'color' => LabelColor::GREEN],
            ['key' => 'bug', 'name' => 'Fehler', 'category' => 'type', 'color' => LabelColor::RED],
            ['key' => 'blocked', 'name' => 'Blockiert', 'category' => 'status', 'color' => LabelColor::YELLOW],
        ],
        'event' => [
            ['key' => 'location', 'name' => 'Location', 'category' => 'trade', 'color' => LabelColor::PURPLE],
            ['key' => 'speakers', 'name' => 'Speaker', 'category' => 'trade', 'color' => LabelColor::CYAN],
            ['key' => 'marketing', 'name' => 'Marketing', 'category' => 'trade', 'color' => LabelColor::ORANGE],
            ['key' => 'confirmed', 'name' => 'Bestätigt', 'category' => 'status', 'color' => LabelColor::GREEN],
        ],
        'podcast' => [
            ['key' => 'topic_idea', 'name' => 'Themenidee', 'category' => 'pipeline', 'color' => LabelColor::INDIGO],
            ['key' => 'guest_booking', 'name' => 'Gästebuchung', 'category' => 'pipeline', 'color' => LabelColor::CYAN],
            ['key' => 'audio_editing', 'name' => 'Audioschnitt', 'category' => 'pipeline', 'color' => LabelColor::YELLOW],
            ['key' => 'social_media', 'name' => 'Social Media', 'category' => 'promotion', 'color' => LabelColor::PINK],
        ],
    ];

    /** German names of the sets (fallback; shown names come from the translations). */
    private const NAMES = [
        'software_dev' => 'Softwareentwicklung',
        'event' => 'Event',
        'podcast' => 'Podcast',
    ];

    /**
     * Creates or updates the built-in sets and their labels. Safe to run
     * again: sets and labels are matched by their key.
     */
    public static function sync(): void
    {
        DB::transaction(function (): void {
            foreach (self::SETS as $setKey => $labels) {
                $set = LabelSet::query()->firstOrNew(['key' => $setKey]);
                $set->forceFill(['key' => $setKey, 'name' => self::NAMES[$setKey], 'description' => null, 'created_by' => null])->save();

                foreach ($labels as $position => $label) {
                    $model = Label::query()->firstOrNew(['label_set_id' => $set->getKey(), 'key' => $label['key']]);
                    $model->forceFill([
                        'label_set_id' => $set->getKey(),
                        'key' => $label['key'],
                        'name' => $label['name'],
                        'category' => $label['category'],
                        'color' => $label['color'],
                        'position' => $position,
                    ])->save();
                }
            }
        });
    }
}
