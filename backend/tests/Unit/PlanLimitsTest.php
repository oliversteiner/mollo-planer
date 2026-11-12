<?php

namespace Tests\Unit;

use App\Plan\PlanSchema;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The GraphQL plan input (graphql/plan.graphql) and the MCP tools both limit
 * the plan. The limits are written in PlanSchema; this test reads the @rules of
 * the GraphQL inputs and checks that they say the same.
 */
class PlanLimitsTest extends TestCase
{
    /**
     * @return array<string, array{string, string, int}>
     */
    public static function limits(): array
    {
        return [
            'plan name' => ['ProjectPlanInput', 'name', PlanSchema::PLAN_NAME_MAX],
            'plan description' => ['ProjectPlanInput', 'description', PlanSchema::PLAN_DESCRIPTION_MAX],
            'members' => ['ProjectPlanInput', 'members', PlanSchema::MAX_MEMBERS],
            'milestones' => ['ProjectPlanInput', 'milestones', PlanSchema::MAX_MILESTONES],
            'milestone title' => ['ProjectPlanMilestoneInput', 'title', PlanSchema::TITLE_MAX],
            'milestone description' => ['ProjectPlanMilestoneInput', 'description', PlanSchema::TEXT_MAX],
            'todos' => ['ProjectPlanMilestoneInput', 'todos', PlanSchema::MAX_TODOS],
            'todo title' => ['ProjectPlanTodoInput', 'title', PlanSchema::TITLE_MAX],
            'todo description' => ['ProjectPlanTodoInput', 'description', PlanSchema::TEXT_MAX],
            'tasks' => ['ProjectPlanTodoInput', 'tasks', PlanSchema::MAX_TASKS],
            'task title' => ['ProjectPlanTaskInput', 'title', PlanSchema::TITLE_MAX],
            'task description' => ['ProjectPlanTaskInput', 'description', PlanSchema::TEXT_MAX],
        ];
    }

    #[DataProvider('limits')]
    public function test_graphql_rule_matches_plan_schema(string $type, string $field, int $expected): void
    {
        $this->assertSame(['max:'.$expected], $this->rules($type, $field, 'max:'));
    }

    public function test_titles_have_the_minimum_length_of_the_plan_schema(): void
    {
        foreach (['ProjectPlanInput.name' => 'name', 'ProjectPlanMilestoneInput.title' => 'title'] as $where => $field) {
            [$type] = explode('.', $where);
            $this->assertContains('min:'.PlanSchema::TITLE_MIN, $this->rules($type, $field, 'min:'), $where);
        }
    }

    /**
     * The max or min rules of one input field, from the @rules directive.
     *
     * @return list<string>
     */
    private function rules(string $typeName, string $fieldName, string $prefix): array
    {
        $document = Parser::parse(file_get_contents(dirname(__DIR__, 2).'/graphql/plan.graphql'));

        foreach ($document->definitions as $definition) {
            if (! $definition instanceof InputObjectTypeDefinitionNode || $definition->name->value !== $typeName) {
                continue;
            }

            foreach ($definition->fields as $field) {
                if ($field->name->value !== $fieldName) {
                    continue;
                }

                $rules = [];

                foreach ($field->directives as $directive) {
                    if ($directive->name->value !== 'rules' && $directive->name->value !== 'rulesForArray') {
                        continue;
                    }

                    /** @var ListValueNode $apply */
                    $apply = $directive->arguments[0]->value;

                    foreach ($apply->values as $value) {
                        /** @var StringValueNode $value */
                        if (str_starts_with($value->value, $prefix)) {
                            $rules[] = $value->value;
                        }
                    }
                }

                return $rules;
            }
        }

        $this->fail("{$typeName}.{$fieldName} is not in plan.graphql.");
    }
}
