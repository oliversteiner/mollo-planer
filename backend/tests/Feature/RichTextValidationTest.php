<?php

namespace Tests\Feature;

use App\GraphQL\Scalars\RichText;
use App\Models\Comment;
use App\Models\Milestone;
use App\Rules\FontAwesomeIconRule;
use App\Rules\RichTextRule;
use GraphQL\Error\Error;
use GraphQL\Language\Parser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RichTextValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_accepts_a_valid_document_and_null(): void
    {
        $this->assertTrue($this->validate(['description' => $this->doc('Hallo')])->passes());
        $this->assertTrue($this->validate(['description' => null])->passes());
    }

    public function test_rule_reports_a_translated_error_per_field(): void
    {
        app()->setLocale('de');
        $html = ['type' => 'doc', 'content' => [['type' => 'image']]];

        $validator = Validator::make(
            ['input' => ['description' => $html]],
            ['input.description' => [new RichTextRule]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('„image“', $validator->errors()->first('input.description'));
    }

    public function test_empty_description_is_allowed(): void
    {
        $this->assertTrue($this->validate(['description' => $this->emptyDoc()])->passes());
    }

    public function test_empty_comment_body_is_rejected_when_required(): void
    {
        $validator = Validator::make(
            ['input' => ['body' => $this->emptyDoc()]],
            ['input.body' => [new RichTextRule, 'required']],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            __('richtext.empty', ['attribute' => 'input.body']),
            $validator->errors()->first('input.body'),
        );
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function icons(): array
    {
        return [
            'simple' => ['rocket', true],
            'with hyphen' => ['rocket-launch', true],
            'with digits' => ['face-smile-2', true],
            'null' => [null, true],
            'uppercase' => ['Rocket', false],
            'style prefix' => ['fa-duotone fa-rocket', false],
            'leading hyphen' => ['-rocket', false],
            'double hyphen' => ['rocket--launch', false],
            'underscore' => ['rocket_launch', false],
            'markup' => ['<svg>', false],
            'too long' => [str_repeat('a', 61), false],
            'not a string' => [42, false],
        ];
    }

    #[DataProvider('icons')]
    public function test_icon_name_format(mixed $icon, bool $valid): void
    {
        $validator = Validator::make(['icon' => $icon], ['icon' => [new FontAwesomeIconRule]]);

        $this->assertSame($valid, $validator->passes());
    }

    public function test_cast_stores_empty_document_as_null(): void
    {
        $milestone = Milestone::factory()->create(['description' => $this->emptyDoc()]);

        $this->assertNull(DB::table('milestones')->where('id', $milestone->id)->value('description'));
        $this->assertNull($milestone->fresh()->description);
    }

    public function test_cast_stores_json_and_strips_link_attributes(): void
    {
        $doc = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://mollo.ch', 'target' => '_blank']]]],
        ]]]];

        $milestone = Milestone::factory()->create(['description' => $doc]);

        $this->assertSame(
            ['href' => 'https://mollo.ch'],
            $milestone->fresh()->description['content'][0]['content'][0]['marks'][0]['attrs'],
        );
    }

    public function test_cast_refuses_documents_outside_the_whitelist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Milestone::factory()->create(['description' => ['type' => 'doc', 'content' => [['type' => 'image']]]]);
    }

    public function test_comment_body_round_trip(): void
    {
        $comment = Comment::factory()->create(['body' => $this->doc('Grüezi')]);

        $this->assertSame('Grüezi', $comment->fresh()->body['content'][0]['content'][0]['text']);
    }

    public function test_scalar_accepts_objects_only(): void
    {
        $scalar = new RichText;

        $this->assertSame($this->doc('x'), $scalar->parseValue($this->doc('x')));
        $this->assertSame($this->doc('x'), $scalar->serialize(json_encode($this->doc('x'))));
        $this->assertNull($scalar->serialize(null));

        foreach (['<p>x</p>', 42, [1, 2]] as $invalid) {
            try {
                $scalar->parseValue($invalid);
                $this->fail('Accepted '.json_encode($invalid));
            } catch (Error) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_scalar_parses_object_literals(): void
    {
        $node = Parser::parseValue('{type: "doc", content: [{type: "paragraph"}]}');

        $this->assertSame(
            ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            (new RichText)->parseLiteral($node),
        );

        $this->expectException(Error::class);
        (new RichText)->parseLiteral(Parser::parseValue('"<p>x</p>"'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, ['description' => [new RichTextRule]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function doc(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDoc(): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph']]];
    }
}
