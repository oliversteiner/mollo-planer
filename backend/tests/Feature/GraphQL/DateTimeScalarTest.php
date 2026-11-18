<?php

namespace Tests\Feature\GraphQL;

use App\GraphQL\Scalars\DateTime;
use Illuminate\Support\Carbon;

/**
 * DateTime has its time zone (docs/fix-m5.md F8.1): out as ISO 8601, in both forms.
 */
class DateTimeScalarTest extends GraphQLTestCase
{
    public function test_a_datetime_comes_out_with_its_zone(): void
    {
        $this->project->forceFill(['created_at' => '2026-11-18 02:13:49'])->saveQuietly();

        $value = $this->as('owner')->gql('query ($id: ID!) { project(id: $id) { createdAt } }', ['id' => $this->project->id])
            ->assertJsonMissingPath('errors')
            ->json('data.project.createdAt');

        $this->assertSame('2026-11-18T02:13:49+00:00', $value);
        $this->assertSame('2026-11-18T03:13:49+01:00', Carbon::parse($value)->setTimezone('Europe/Zurich')->toIso8601String());
    }

    public function test_both_forms_are_read_as_the_same_moment(): void
    {
        $scalar = app(DateTime::class);

        foreach (['2026-11-18T02:13:49+00:00', '2026-11-18T02:13:49Z', '2026-11-18T03:13:49+01:00', '2026-11-18 02:13:49'] as $text) {
            $this->assertSame('2026-11-18T02:13:49+00:00', $scalar->serialize($scalar->parseValue($text)), $text);
        }
    }

    public function test_a_value_in_another_zone_is_written_in_utc(): void
    {
        $this->assertSame('2026-11-18T02:13:49+00:00', app(DateTime::class)->serialize(Carbon::parse('2026-11-18 03:13:49', 'Europe/Zurich')));
    }
}
