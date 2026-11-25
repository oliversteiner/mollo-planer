<?php

namespace Tests\Feature\GraphQL;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Enums\ProjectRole;
use App\GraphQL\Errors\BillingRefusal;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Milestone;
use App\Models\Project;
use App\Services\Billing\InvoiceService;
use App\Services\Deletion;
use App\Support\Billing\CreditorReference;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Billing, part B (Auftrag 39): billable milestones, drafts, issuing, paid, cancelled.
 */
class BillingInvoicesTest extends GraphQLTestCase
{
    private const INVOICE = 'id number status overdue totalCents reference issueDate dueDate locale notes recipient { name city } sender { name iban } items { id projectId projectName milestoneId title description amountCents }';

    private Customer $customer;

    private Project $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create(['company' => 'Muster AG', 'name' => 'Hans Muster', 'city' => 'Zürich', 'locale' => 'de']);
        $this->project->forceFill(['customer_id' => $this->customer->id])->save();
        $this->second = Project::factory()->withMember($this->users['owner'], ProjectRole::OWNER)->create(['name' => 'Club DB']);
        $this->second->forceFill(['customer_id' => $this->customer->id])->save();

        BillingSetting::current()->fill([
            'name' => 'Mollo', 'street' => 'Weg', 'house_number' => '2', 'postal_code' => '3000', 'city' => 'Bern',
            'country' => 'CH', 'iban' => 'CH9300762011623852957',
        ])->save();
    }

    private function done(Project $project, string $title, ?int $price = 100000, array $attributes = []): Milestone
    {
        $milestone = Milestone::factory()->for($project)->create(['title' => $title] + $attributes);
        $milestone->forceFill(['status' => ItemStatus::DONE, 'price_cents' => $price])->saveQuietly();

        return $milestone;
    }

    /**
     * @param  list<Milestone>  $milestones
     */
    private function draft(array $milestones, ?Customer $customer = null): TestResponse
    {
        return $this->as('admin')->gql('mutation ($c: ID!, $m: [ID!]!) { createInvoiceDraft(customerId: $c, milestoneIds: $m, notes: "Danke") { '.self::INVOICE.' } }', [
            'c' => ($customer ?? $this->customer)->id,
            'm' => array_map(fn (Milestone $milestone) => $milestone->id, $milestones),
        ]);
    }

    private function issue(string $id, ?string $date = '2026-11-25'): TestResponse
    {
        return $this->as('admin')->gql('mutation ($id: ID!, $d: Date) { issueInvoice(id: $id, issueDate: $d) { '.self::INVOICE.' } }', ['id' => $id, 'd' => $date]);
    }

    public function test_only_done_priced_milestones_of_projects_with_a_customer_are_billable(): void
    {
        $billable = $this->done($this->project, 'Version 1.0', 150000);
        $this->done($this->second, 'Login', 50000);
        $this->done($this->project, 'Ohne Preis', null);
        Milestone::factory()->for($this->project)->create(['title' => 'Offen'])->forceFill(['price_cents' => 1])->saveQuietly();
        $noCustomer = Project::factory()->create(['name' => 'Intern']);
        $this->done($noCustomer, 'Intern fertig', 1000);
        $trashed = $this->done($this->project, 'Im Papierkorb');
        app(Deletion::class)->delete($trashed);

        $data = $this->as('admin')->gql('{ billingOverview { groups { customer { name } totalCents projects { projectName totalCents milestones { id title priceCents } } } withoutCustomer { projectName milestoneTitle } withoutPrice { projectName milestoneTitle } } }')
            ->assertJsonMissingPath('errors')
            ->json('data.billingOverview');

        $this->assertCount(1, $data['groups']);
        $this->assertSame(200000, $data['groups'][0]['totalCents']);
        $this->assertSame(['Club DB', 'Mollo Planer'], array_column($data['groups'][0]['projects'], 'projectName'));
        $this->assertSame([['id' => (string) $billable->id, 'title' => 'Version 1.0', 'priceCents' => 150000]], $data['groups'][0]['projects'][1]['milestones']);
        $this->assertSame([['projectName' => 'Intern', 'milestoneTitle' => 'Intern fertig']], $data['withoutCustomer']);
        $this->assertSame([['projectName' => 'Mollo Planer', 'milestoneTitle' => 'Ohne Preis']], $data['withoutPrice']);
    }

    public function test_a_draft_holds_milestones_of_several_projects_of_one_customer_grouped_by_project(): void
    {
        $a = $this->done($this->project, 'Version 1.0', 150000, ['description' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Erste Fassung']]]]]]);
        $b = $this->done($this->second, 'Login', 50000);

        $draft = $this->draft([$a, $b])->assertJsonMissingPath('errors')->json('data.createInvoiceDraft');

        $this->assertSame('DRAFT', $draft['status']);
        $this->assertNull($draft['number']);
        $this->assertSame(200000, $draft['totalCents']);
        $this->assertSame('Danke', $draft['notes']);
        $this->assertSame(['Club DB', 'Mollo Planer'], array_column($draft['items'], 'projectName'));
        $this->assertSame('Erste Fassung', $draft['items'][1]['description']);

        $this->as('admin')->gql('{ billingOverview { groups { totalCents } } }')->assertJsonPath('data.billingOverview.groups', []);
    }

    public function test_a_milestone_goes_into_one_invoice_only(): void
    {
        $milestone = $this->done($this->project, 'Version 1.0');
        $this->draft([$milestone])->assertJsonMissingPath('errors');

        $this->draft([$milestone])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::NOT_BILLABLE)
            ->assertJsonPath('errors.0.extensions.milestoneIds', [(string) $milestone->id]);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_two_drafts_at_the_same_moment_cannot_both_take_a_milestone(): void
    {
        $milestone = $this->done($this->project, 'Version 1.0');
        $first = $this->draft([$milestone])->json('data.createInvoiceDraft.id');

        // What the second, simultaneous draft does after its check passed: the unique column refuses it.
        $this->expectException(UniqueConstraintViolationException::class);
        $item = new InvoiceItem;
        $item->forceFill(['invoice_id' => $first, 'project_name' => 'x', 'milestone_id' => $milestone->id, 'billing_milestone_id' => $milestone->id, 'title' => 'x', 'amount_cents' => 1])->save();
    }

    public function test_the_service_turns_a_lost_race_into_the_same_refusal(): void
    {
        $milestone = $this->done($this->project, 'Version 1.0');
        $service = app(InvoiceService::class);

        // The check sees the milestone free, the write meets the item of the other draft.
        $other = new Invoice;
        $other->forceFill(['customer_id' => $this->customer->id])->save();
        Milestone::query()->whereKey($milestone->id)->update(['price_cents' => 100000]);
        $check = (new \ReflectionClass($service))->getMethod('holding');

        try {
            $check->invoke($service, function () use ($other, $milestone): void {
                foreach ([1, 2] as $copy) {
                    $item = new InvoiceItem;
                    $item->forceFill(['invoice_id' => $other->id, 'project_name' => 'x', 'milestone_id' => $milestone->id, 'billing_milestone_id' => $milestone->id, 'title' => 'x', 'amount_cents' => $copy])->save();
                }
            }, [$milestone->id]);
            $this->fail('No refusal');
        } catch (BillingRefusal $refusal) {
            $this->assertSame(BillingRefusal::NOT_BILLABLE, $refusal->reason);
        }
    }

    public function test_milestones_of_another_customer_or_without_customer_are_refused(): void
    {
        $other = Customer::factory()->create();
        $project = Project::factory()->create(['name' => 'Fremd']);
        $project->forceFill(['customer_id' => $other->id])->save();
        $foreign = $this->done($project, 'Fremd fertig');
        $own = $this->done($this->project, 'Eigen');

        $this->draft([$own, $foreign])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::OTHER_CUSTOMER)
            ->assertJsonPath('errors.0.extensions.titles', ['Fremd fertig']);

        $none = Project::factory()->create(['name' => 'Ohne Kunde']);
        $this->draft([$this->done($none, 'X')])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::CUSTOMER_MISSING)
            ->assertJsonPath('errors.0.extensions.projects', ['Ohne Kunde']);

        $this->draft([])->assertJsonPath('errors.0.extensions.code', BillingRefusal::EMPTY);
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_the_total_stays_within_the_limit_of_the_qr_bill(): void
    {
        $this->draft([$this->done($this->project, 'A', 600000000), $this->done($this->project, 'B', 400000000)])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::TOTAL_TOO_HIGH);
    }

    public function test_a_draft_changes_its_texts_and_loses_items_and_the_milestones_are_free_again(): void
    {
        $a = $this->done($this->project, 'A', 1000);
        $b = $this->done($this->project, 'B', 2000);
        $draft = $this->draft([$a, $b])->json('data.createInvoiceDraft');

        $changed = $this->as('admin')->gql('mutation ($id: ID!, $items: [InvoiceItemInput!]) { updateInvoiceDraft(id: $id, notes: "", items: $items) { notes totalCents items { title description } } }', [
            'id' => $draft['id'],
            'items' => [['id' => $draft['items'][1]['id'], 'description' => 'Neu']],
        ])->assertJsonMissingPath('errors')->json('data.updateInvoiceDraft');

        $this->assertNull($changed['notes']);
        $this->assertSame(2000, $changed['totalCents']);
        $this->assertSame([['title' => 'B', 'description' => 'Neu']], $changed['items']);
        $this->assertSame([(string) $a->id], array_column($this->as('admin')->gql('{ billingOverview { groups { projects { milestones { id } } } } }')->json('data.billingOverview.groups.0.projects.0.milestones'), 'id'));

        $this->as('admin')->gql('mutation ($id: ID!) { updateInvoiceDraft(id: $id, items: []) { id } }', ['id' => $draft['id']])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::EMPTY);

        $this->as('admin')->gql('mutation ($id: ID!) { deleteInvoiceDraft(id: $id) }', ['id' => $draft['id']])
            ->assertJsonPath('data.deleteInvoiceDraft', $draft['id']);
        $this->assertSame(0, InvoiceItem::query()->count());
    }

    public function test_issuing_gives_the_number_freezes_and_derives_dates_and_reference(): void
    {
        $milestone = $this->done($this->project, 'Version 1.0', 150000);
        $id = $this->draft([$milestone])->json('data.createInvoiceDraft.id');

        $invoice = $this->issue($id)->assertJsonMissingPath('errors')->json('data.issueInvoice');

        $this->assertSame('ISSUED', $invoice['status']);
        $this->assertSame('2026-0001', $invoice['number']);
        $this->assertSame(CreditorReference::fromNumber('2026-0001'), $invoice['reference']);
        $this->assertTrue(CreditorReference::isValid($invoice['reference']));
        $this->assertSame('2026-11-25', $invoice['issueDate']);
        $this->assertSame('2026-12-25', $invoice['dueDate']);
        $this->assertSame(['name' => 'Hans Muster', 'city' => 'Zürich'], $invoice['recipient']);
        $this->assertSame(['name' => 'Mollo', 'iban' => 'CH9300762011623852957'], $invoice['sender']);
        $this->assertSame($this->users['admin']->id, Invoice::query()->find($id)->issued_by);

        // Nothing of today changes it any more.
        $this->customer->forceFill(['city' => 'Basel', 'name' => 'Neu'])->save();
        $this->project->forceFill(['name' => 'Umbenannt'])->save();
        $milestone->forceFill(['title' => 'Anders', 'price_cents' => 1])->saveQuietly();
        BillingSetting::current()->forceFill(['iban' => 'LI21 0881 0000 2324 013A A', 'name' => 'Andere'])->save();

        $again = $this->as('admin')->gql('query ($id: ID!) { invoice(id: $id) { '.self::INVOICE.' } }', ['id' => $id])->json('data.invoice');
        $this->assertSame(['name' => 'Hans Muster', 'city' => 'Zürich'], $again['recipient']);
        $this->assertSame(['name' => 'Mollo', 'iban' => 'CH9300762011623852957'], $again['sender']);
        $this->assertSame('Mollo Planer', $again['items'][0]['projectName']);
        $this->assertSame('Version 1.0', $again['items'][0]['title']);
        $this->assertSame(150000, $again['totalCents']);

        $this->as('admin')->gql('mutation ($id: ID!) { updateInvoiceDraft(id: $id, notes: "x") { id } }', ['id' => $id])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::WRONG_STATUS);
        $this->as('admin')->gql('mutation ($id: ID!) { deleteInvoiceDraft(id: $id) }', ['id' => $id])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::WRONG_STATUS);
        $this->issue($id)->assertJsonPath('errors.0.extensions.code', BillingRefusal::WRONG_STATUS);
    }

    public function test_the_project_name_is_taken_when_issued_not_when_drafted(): void
    {
        $id = $this->draft([$this->done($this->project, 'A')])->json('data.createInvoiceDraft.id');
        $this->project->forceFill(['name' => 'Mollo Planer 2'])->save();

        $this->assertSame('Mollo Planer 2', $this->issue($id)->json('data.issueInvoice.items.0.projectName'));
    }

    public function test_numbers_have_no_gaps_and_start_again_each_year(): void
    {
        $numbers = [];

        foreach (['2026-03-01', '2026-11-25', '2026-12-31', '2027-01-02', '2027-02-01'] as $date) {
            $id = $this->draft([$this->done($this->project, 'M '.$date)])->json('data.createInvoiceDraft.id');
            $numbers[] = $this->issue($id, $date)->json('data.issueInvoice.number');
        }

        $this->assertSame(['2026-0001', '2026-0002', '2026-0003', '2027-0001', '2027-0002'], $numbers);
    }

    public function test_a_failed_issue_gives_its_number_back(): void
    {
        $id = $this->draft([$this->done($this->project, 'A')])->json('data.createInvoiceDraft.id');
        BillingSetting::current()->forceFill(['iban' => null])->save();

        $this->issue($id)
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::SENDER_INCOMPLETE)
            ->assertJsonPath('errors.0.extensions.missingFields', ['iban']);

        BillingSetting::current()->forceFill(['iban' => 'CH9300762011623852957'])->save();
        $this->assertSame('2026-0001', $this->issue($id)->json('data.issueInvoice.number'));
    }

    public function test_a_milestone_reopened_or_deleted_after_the_draft_stops_the_issue(): void
    {
        $milestone = $this->done($this->project, 'Wieder offen');
        $id = $this->draft([$milestone])->json('data.createInvoiceDraft.id');
        $milestone->forceFill(['status' => ItemStatus::OPEN])->saveQuietly();

        $this->issue($id)
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::NOT_BILLABLE)
            ->assertJsonPath('errors.0.extensions.titles', ['Wieder offen']);
        $this->assertNull(Invoice::query()->find($id)->number);
    }

    public function test_a_number_format_without_year_runs_on(): void
    {
        BillingSetting::current()->forceFill(['number_format' => 'R{NNNNN}'])->save();

        $first = $this->issue($this->draft([$this->done($this->project, 'A')])->json('data.createInvoiceDraft.id'), '2026-12-31')->json('data.issueInvoice.number');
        $second = $this->issue($this->draft([$this->done($this->project, 'B')])->json('data.createInvoiceDraft.id'), '2027-01-01')->json('data.issueInvoice.number');

        $this->assertSame(['R00001', 'R00002'], [$first, $second]);
    }

    public function test_paid_and_overdue(): void
    {
        $id = $this->issue($this->draft([$this->done($this->project, 'A', 1000)])->json('data.createInvoiceDraft.id'), '2026-01-01')->json('data.issueInvoice.id');
        $this->issue($this->draft([$this->done($this->project, 'B', 2000)])->json('data.createInvoiceDraft.id'), Carbon::today()->toDateString());

        $this->assertTrue($this->as('admin')->gql('query ($id: ID!) { invoice(id: $id) { overdue } }', ['id' => $id])->json('data.invoice.overdue'));
        $this->as('admin')->gql('{ invoiceTotals { openCount openCents overdueCount overdueCents } }')
            ->assertJsonPath('data.invoiceTotals', ['openCount' => 2, 'openCents' => 3000, 'overdueCount' => 1, 'overdueCents' => 1000]);

        $this->as('admin')->gql('mutation ($id: ID!) { markInvoicePaid(id: $id, paidAt: "2026-02-03") { status overdue } }', ['id' => $id])
            ->assertJsonPath('data.markInvoicePaid', ['status' => 'PAID', 'overdue' => false]);
        $this->assertSame('2026-02-03', Invoice::query()->find($id)->paid_at->toDateString());

        $this->as('admin')->gql('mutation ($id: ID!) { markInvoicePaid(id: $id, paidAt: "2026-02-03") { status } }', ['id' => $id])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::WRONG_STATUS);
        $this->as('admin')->gql('{ invoiceTotals { openCount } }')->assertJsonPath('data.invoiceTotals.openCount', 1);
    }

    public function test_cancelling_keeps_the_number_and_frees_the_milestones(): void
    {
        $milestone = $this->done($this->project, 'A');
        $id = $this->issue($this->draft([$milestone])->json('data.createInvoiceDraft.id'))->json('data.issueInvoice.id');

        $this->as('admin')->gql('mutation ($id: ID!) { cancelInvoice(id: $id, reason: "x") { id } }', ['id' => $id])
            ->assertJsonPath('errors.0.extensions.validation.reason.0', fn ($message) => is_string($message));
        $this->as('admin')->gql('mutation ($id: ID!) { cancelInvoice(id: $id, reason: "Falscher Betrag") { status cancelReason number } }', ['id' => $id])
            ->assertJsonPath('data.cancelInvoice', ['status' => 'CANCELLED', 'cancelReason' => 'Falscher Betrag', 'number' => '2026-0001']);

        $again = $this->draft([$milestone])->assertJsonMissingPath('errors')->json('data.createInvoiceDraft.id');
        $this->assertSame('2026-0002', $this->issue($again)->json('data.issueInvoice.number'));
        $this->assertSame(2, Invoice::query()->whereNotNull('number')->count());

        $this->as('admin')->gql('mutation ($id: ID!) { cancelInvoice(id: $id, reason: "Nochmals") { id } }', ['id' => $id])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::WRONG_STATUS);
    }

    public function test_a_project_or_milestone_with_an_issued_invoice_can_still_be_deleted(): void
    {
        $milestone = $this->done($this->project, 'A');
        $id = $this->issue($this->draft([$milestone])->json('data.createInvoiceDraft.id'))->json('data.issueInvoice.id');

        $milestone->forceDelete();
        $this->project->forceDelete();

        $invoice = $this->as('admin')->gql('query ($id: ID!) { invoice(id: $id) { items { projectId milestoneId projectName title } } }', ['id' => $id])->json('data.invoice');
        $this->assertSame([['projectId' => null, 'milestoneId' => null, 'projectName' => 'Mollo Planer', 'title' => 'A']], $invoice['items']);
    }

    public function test_the_list_filters_by_status_customer_and_year(): void
    {
        $this->issue($this->draft([$this->done($this->project, 'A')])->json('data.createInvoiceDraft.id'), '2025-06-01');
        $this->issue($this->draft([$this->done($this->project, 'B')])->json('data.createInvoiceDraft.id'), '2026-06-01');
        $this->draft([$this->done($this->project, 'C')]);

        $all = $this->as('admin')->gql('{ invoices { number status } }')->json('data.invoices');
        $this->assertSame([null, '2026-0001', '2025-0001'], array_column($all, 'number'));
        $this->assertSame(['2026-0001'], array_column($this->as('admin')->gql('{ invoices(year: 2026) { number } }')->json('data.invoices'), 'number'));
        $this->assertSame([null], array_column($this->as('admin')->gql('{ invoices(status: DRAFT) { number } }')->json('data.invoices'), 'number'));
        $this->assertSame([], $this->as('admin')->gql('query ($c: ID) { invoices(customerId: $c) { number } }', ['c' => Customer::factory()->create()->id])->json('data.invoices'));
    }

    public function test_without_billing_no_invoice_is_seen_or_made(): void
    {
        $milestone = $this->done($this->project, 'A');

        foreach (['{ invoices { id } }', '{ invoiceTotals { openCount } }', '{ billingOverview { groups { totalCents } } }'] as $query) {
            $this->as('owner')->gql($query)->assertJsonPath('errors.0.message', __('graphql.unauthorized'));
        }

        $this->as('owner')->gql('mutation ($c: ID!, $m: [ID!]!) { createInvoiceDraft(customerId: $c, milestoneIds: $m) { id } }', ['c' => $this->customer->id, 'm' => [$milestone->id]])
            ->assertJsonPath('errors.0.message', __('graphql.unauthorized'));
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(InvoiceStatus::DRAFT, (new Invoice)->status);
    }

    public function test_a_second_issue_at_the_same_moment_waits_for_the_number_range(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite has no row locks. Run composer test:mariadb for this test.');
        }

        // This connection issues an invoice and still holds its transaction (the test transaction).
        $id = $this->draft([$this->done($this->project, 'A')])->json('data.createInvoiceDraft.id');
        $this->assertSame('2026-0001', $this->issue($id)->json('data.issueInvoice.number'));

        // A second connection, as a second request at the same moment, must wait for the number range of 2026.
        config(['database.connections.second' => config('database.connections.'.DB::getDefaultConnection())]);
        $second = DB::connection('second');
        $second->statement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $second->transaction(fn () => $second->table('invoice_number_sequences')->where('year', 2026)->lockForUpdate()->value('last_number'));
            $this->fail('The second issuer did not wait for the lock on the number range.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('Lock wait timeout', $error->getMessage());
        } finally {
            DB::purge('second');
        }
    }
}
