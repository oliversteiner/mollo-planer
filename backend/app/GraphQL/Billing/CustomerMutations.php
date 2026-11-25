<?php

namespace App\GraphQL\Billing;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Customer;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Customers, the customer of a project and the price of a milestone (Auftrag 39 A). billing.manage is checked by
 *
 * @can; neither customer_id nor price_cents is fillable, so no other mutation (and no import) can set them.
 */
class CustomerMutations
{
    use ResolvesViewer;

    /**
     * @param  array{input: array<string, mixed>}  $args
     */
    public function create(mixed $root, array $args, GraphQLContext $context): Customer
    {
        $customer = new Customer(self::attributes($args['input']));
        $customer->forceFill(['created_by' => $this->viewer($context)->getKey()])->save();

        return $customer;
    }

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function update(mixed $root, array $args): Customer
    {
        $customer = Customer::query()->findOrFail($args['id']);
        $customer->fill(self::attributes($args['input']))->save();

        return $customer;
    }

    /**
     * @param  array{id: string, archived: bool}  $args
     */
    public function archive(mixed $root, array $args): Customer
    {
        $customer = Customer::query()->findOrFail($args['id']);
        $customer->forceFill(['archived_at' => $args['archived'] ? ($customer->archived_at ?? now()) : null])->save();

        return $customer;
    }

    /**
     * @param  array{projectId: string, customerId?: string|null}  $args
     *
     * @throws ValidationException
     */
    public function setProjectCustomer(mixed $root, array $args): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $customer = isset($args['customerId']) ? Customer::query()->findOrFail($args['customerId']) : null;

        if ($customer?->isArchived() && (int) $project->customer_id !== $customer->id) {
            throw ValidationException::withMessages(['customerId' => __('billing.customer_archived')]);
        }

        $project->forceFill(['customer_id' => $customer?->id])->save();

        return $project;
    }

    /**
     * @param  array{milestoneId: string, priceCents?: int|null}  $args
     */
    public function setMilestonePrice(mixed $root, array $args): Milestone
    {
        $milestone = Milestone::query()->findOrFail($args['milestoneId']);
        $milestone->forceFill(['price_cents' => $args['priceCents'] ?? null])->save();

        return $milestone;
    }

    /**
     * Empty optional texts are stored as null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function attributes(array $input): array
    {
        return array_map(fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value), $input);
    }
}
