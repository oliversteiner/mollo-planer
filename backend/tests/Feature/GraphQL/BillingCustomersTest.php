<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Support\Facades\DB;

/**
 * Billing, part A (Auftrag 39): customers, the customer of a project, the price of a milestone, the sender. Everything
 * only with billing.manage, independent of project roles; agents never.
 */
class BillingCustomersTest extends GraphQLTestCase
{
    private const CUSTOMER_FIELDS = 'id company name street houseNumber postalCode city country email locale notes archived';

    private const CREATE = 'mutation ($input: CustomerInput!) { createCustomer(input: $input) { '.self::CUSTOMER_FIELDS.' } }';

    /** @return array<string, mixed> */
    private function address(array $overrides = []): array
    {
        return array_merge([
            'company' => 'Muster AG',
            'name' => 'Hans Muster',
            'street' => 'Bahnhofstrasse',
            'houseNumber' => '1',
            'postalCode' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
            'email' => 'hans@muster.example',
            'locale' => 'DE',
            'notes' => 'zahlt pünktlich',
        ], $overrides);
    }

    /**
     * The first message of a field in errors.0.extensions.validation, whatever prefix the key has (input.city, city).
     */
    private static function field(string $name, string|\Closure $expected): \Closure
    {
        return function (array $validation) use ($name, $expected): bool {
            foreach ($validation as $key => $messages) {
                if ($key === $name || str_ends_with((string) $key, '.'.$name)) {
                    return is_string($expected) ? $messages[0] === $expected : $expected($messages[0]);
                }
            }

            return false;
        };
    }

    public function test_the_admin_has_billing_and_the_other_system_roles_do_not(): void
    {
        $this->assertTrue(Rights::allows($this->users['admin'], Right::BILLING_MANAGE));
        $this->assertTrue(Right::BILLING_MANAGE->isSystem());

        foreach (['projektleitung', 'koordination', 'lesend', 'owner'] as $name) {
            $this->assertFalse(Rights::allows($this->users[$name], Right::BILLING_MANAGE), $name);
        }
    }

    public function test_an_admin_creates_changes_and_archives_a_customer(): void
    {
        $id = $this->as('admin')->gql(self::CREATE, ['input' => $this->address()])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.createCustomer.city', 'Zürich')
            ->assertJsonPath('data.createCustomer.locale', 'DE')
            ->json('data.createCustomer.id');

        $this->assertSame($this->users['admin']->id, Customer::query()->find($id)->created_by);

        $this->as('admin')->gql('mutation ($id: ID!, $input: CustomerInput!) { updateCustomer(id: $id, input: $input) { city company houseNumber } }', [
            'id' => $id,
            'input' => $this->address(['city' => 'Bern', 'postalCode' => '3011', 'company' => '  ', 'houseNumber' => null]),
        ])->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateCustomer.city', 'Bern')
            ->assertJsonPath('data.updateCustomer.company', null);

        $this->as('admin')->gql('mutation ($id: ID!) { archiveCustomer(id: $id, archived: true) { archived } }', ['id' => $id])
            ->assertJsonPath('data.archiveCustomer.archived', true);

        $this->as('admin')->gql('{ customers { id } }')->assertJsonPath('data.customers', []);
        $this->as('admin')->gql('{ customers(archived: true) { id } }')->assertJsonPath('data.customers.0.id', (string) $id);
    }

    public function test_the_address_must_fit_the_qr_bill(): void
    {
        $this->as('admin')->gql(self::CREATE, ['input' => $this->address(['postalCode' => '80011'])])
            ->assertJsonPath('errors.0.extensions.validation', self::field('postalCode', __('billing.postal_code', ['country' => 'CH'])));

        $this->as('admin')->gql(self::CREATE, ['input' => $this->address(['postalCode' => '10115', 'country' => 'DE'])])
            ->assertJsonMissingPath('errors');

        $this->as('admin')->gql(self::CREATE, ['input' => $this->address(['country' => 'ch'])])
            ->assertJsonPath('errors.0.extensions.validation', self::field('country', fn ($message) => is_string($message)));

        $this->as('admin')->gql(self::CREATE, ['input' => $this->address(['city' => str_repeat('x', 36)])])
            ->assertJsonPath('errors.0.extensions.validation', self::field('city', fn ($message) => is_string($message)));
    }

    public function test_without_billing_nobody_sees_or_changes_customers_even_the_owner_of_the_project(): void
    {
        $customer = Customer::factory()->create();
        $this->project->forceFill(['customer_id' => $customer->id])->save();
        $this->milestone->forceFill(['price_cents' => 150000])->save();

        $this->as('owner')->gql('{ customers { id } }')->assertJsonPath('errors.0.message', __('graphql.unauthorized'));
        $this->as('owner')->gql(self::CREATE, ['input' => $this->address()])->assertJsonPath('errors.0.message', __('graphql.unauthorized'));
        $this->as('owner')->gql('{ billingSettings { iban } }')->assertJsonPath('errors.0.message', __('graphql.unauthorized'));

        $this->as('owner')->gql('query ($id: ID!) { project(id: $id) { name customer { id } milestones { priceCents } } }', ['id' => $this->project->id])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.project.name', 'Mollo Planer')
            ->assertJsonPath('data.project.customer', null)
            ->assertJsonPath('data.project.milestones.0.priceCents', null);

        $this->as('owner')->gql('mutation ($id: ID!) { setMilestonePrice(milestoneId: $id, priceCents: 1) { id } }', ['id' => $this->milestone->id])
            ->assertJsonPath('errors.0.message', __('graphql.unauthorized'));
        $this->assertSame(150000, $this->milestone->fresh()->price_cents);
    }

    public function test_with_billing_the_customer_and_the_price_are_set_and_seen(): void
    {
        $customer = Customer::factory()->create(['name' => 'Kundin']);

        $this->as('admin')->gql('mutation ($p: ID!, $c: ID) { setProjectCustomer(projectId: $p, customerId: $c) { customer { name } } }', ['p' => $this->project->id, 'c' => $customer->id])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.setProjectCustomer.customer.name', 'Kundin');

        $this->as('admin')->gql('mutation ($id: ID!, $price: Int) { setMilestonePrice(milestoneId: $id, priceCents: $price) { priceCents } }', ['id' => $this->milestone->id, 'price' => 999999999])
            ->assertJsonPath('data.setMilestonePrice.priceCents', 999999999);

        $this->as('admin')->gql('mutation ($id: ID!, $price: Int) { setMilestonePrice(milestoneId: $id, priceCents: $price) { priceCents } }', ['id' => $this->milestone->id, 'price' => 1000000000])
            ->assertJsonPath('errors.0.extensions.validation', self::field('priceCents', fn ($message) => is_string($message)));
        $this->as('admin')->gql('mutation ($id: ID!, $price: Int) { setMilestonePrice(milestoneId: $id, priceCents: $price) { priceCents } }', ['id' => $this->milestone->id, 'price' => -1])
            ->assertJsonPath('errors.0.extensions.validation', self::field('priceCents', fn ($message) => is_string($message)));

        $this->as('admin')->gql('mutation ($id: ID!) { setMilestonePrice(milestoneId: $id, priceCents: null) { priceCents } }', ['id' => $this->milestone->id])
            ->assertJsonPath('data.setMilestonePrice.priceCents', null);

        $this->as('admin')->gql('query ($id: ID!) { customer(id: $id) { projects { name } } }', ['id' => $customer->id])
            ->assertJsonPath('data.customer.projects.0.name', 'Mollo Planer');
    }

    public function test_an_archived_customer_is_not_given_to_another_project(): void
    {
        $customer = Customer::factory()->archived()->create();

        $this->as('admin')->gql('mutation ($p: ID!, $c: ID) { setProjectCustomer(projectId: $p, customerId: $c) { id } }', ['p' => $this->project->id, 'c' => $customer->id])
            ->assertJsonPath('errors.0.extensions.validation', self::field('customerId', __('billing.customer_archived')));
    }

    public function test_the_usual_mutations_cannot_set_the_price_or_the_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->as('owner')->gql('mutation ($id: ID!) { updateMilestone(id: $id, input: { title: "Neu" }) { id } }', ['id' => $this->milestone->id])
            ->assertJsonMissingPath('errors');

        $this->milestone->fill(['price_cents' => 5, 'title' => 'x']);
        $this->project->fill(['customer_id' => $customer->id]);
        $this->assertNull($this->milestone->price_cents);
        $this->assertNull($this->project->customer_id);
    }

    public function test_the_sender_needs_a_fresh_password_and_tells_what_is_missing(): void
    {
        $this->as('admin')->gql('{ billingSettings { missingFields paymentTermsDays numberFormat country } }')
            ->assertJsonPath('data.billingSettings.missingFields', ['name', 'street', 'postalCode', 'city', 'iban'])
            ->assertJsonPath('data.billingSettings.paymentTermsDays', 30)
            ->assertJsonPath('data.billingSettings.numberFormat', '{YYYY}-{NNNN}')
            ->assertJsonPath('data.billingSettings.country', 'CH');

        $mutation = 'mutation ($input: BillingSettingsInput!) { updateBillingSettings(input: $input) { iban missingFields } }';
        $input = ['name' => 'Mollo', 'street' => 'Weg', 'houseNumber' => '2', 'postalCode' => '3000', 'city' => 'Bern', 'country' => 'CH', 'iban' => 'ch93 0076 2011 6238 5295 7', 'paymentTermsDays' => 30, 'numberFormat' => '{YYYY}-{NNNN}'];

        $this->as('admin')->gql($mutation, ['input' => $input])
            ->assertJsonPath('errors.0.extensions.validation', self::field('password', __('graphql.password_confirmation_required')));

        $this->as('admin')->withSession(['auth.password_confirmed_at' => time()])->gql($mutation, ['input' => $input])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateBillingSettings.iban', 'CH9300762011623852957')
            ->assertJsonPath('data.updateBillingSettings.missingFields', []);
    }

    public function test_the_iban_and_the_number_format_are_checked(): void
    {
        $mutation = 'mutation ($input: BillingSettingsInput!) { updateBillingSettings(input: $input) { iban } }';
        $base = ['country' => 'CH', 'paymentTermsDays' => 30, 'numberFormat' => '{YYYY}-{NNNN}'];
        $confirmed = fn () => $this->as('admin')->withSession(['auth.password_confirmed_at' => time()]);

        $confirmed()->gql($mutation, ['input' => $base + ['iban' => 'CH9300762011623852958']])
            ->assertJsonPath('errors.0.extensions.validation', self::field('iban', __('billing.iban_checksum')));
        $confirmed()->gql($mutation, ['input' => $base + ['iban' => 'DE89370400440532013000']])
            ->assertJsonPath('errors.0.extensions.validation', self::field('iban', __('billing.iban_country')));
        $confirmed()->gql($mutation, ['input' => $base + ['iban' => 'CH4431999123000889012']])
            ->assertJsonPath('errors.0.extensions.validation', self::field('iban', __('billing.iban_qr')));

        foreach (['{YYYY}', 'R-{NNNN}-{NNNN}', '{NNNN}-{YYYY}-{YYYY}', '{XXXX}-{NNN}'] as $format) {
            $confirmed()->gql($mutation, ['input' => ['numberFormat' => $format] + $base])
                ->assertJsonPath('errors.0.extensions.validation', self::field('numberFormat', __('billing.number_format')));
        }

        foreach (['{NNNN}', 'R{YYYY}{NNNNN}', '{NNN}/{YYYY}'] as $format) {
            $confirmed()->gql($mutation, ['input' => ['numberFormat' => $format] + $base])->assertJsonMissingPath('errors');
        }
    }

    public function test_an_agent_never_has_billing_even_with_a_role_that_carries_it(): void
    {
        $agent = User::factory()->agent()->create();
        DB::table('users')->where('id', $agent->id)->update(['role_id' => $this->systemRole(UserRole::ADMIN)->id]);
        $agent->refresh();
        Rights::clearCache();

        $this->assertFalse(Rights::allows($agent, Right::BILLING_MANAGE));
        $this->assertNotContains(Right::BILLING_MANAGE, Rights::rightsOf($agent));
        $this->assertTrue(Rights::allows($agent, Right::USERS_MANAGE));
    }

    public function test_a_role_with_billing_is_refused_for_an_agent_account(): void
    {
        $agent = User::factory()->agent()->create();

        $this->as('admin')->gql('mutation ($id: ID!) { updateUser(id: $id, input: { role: "admin" }) { id } }', ['id' => $agent->id])
            ->assertJsonPath('errors.0.extensions.validation', fn (array $validation) => str_contains(json_encode($validation, JSON_UNESCAPED_UNICODE), 'Agent'));

        $this->assertNotSame($this->systemRole(UserRole::ADMIN)->id, $agent->fresh()->role_id);

        $person = User::factory()->create();
        $this->as('admin')->gql('mutation ($id: ID!) { updateUser(id: $id, input: { role: "admin" }) { id } }', ['id' => $person->id])
            ->assertJsonMissingPath('errors');
    }

    public function test_a_project_role_never_brings_billing(): void
    {
        foreach (Role::query()->where('scope', Role::SCOPE_PROJECT)->get() as $role) {
            $this->assertNotContains(Right::BILLING_MANAGE, Rights::rightsOfRole($role), $role->key);
        }

        $this->assertFalse(Rights::allows($this->users['owner'], Right::BILLING_MANAGE, $this->project));
        $this->assertSame(ProjectRole::OWNER->value, $this->project->roleOf($this->users['owner'])?->key);
    }
}
