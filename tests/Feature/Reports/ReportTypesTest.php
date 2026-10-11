<?php

namespace Tests\Feature\Reports;

use App\Models\Currency;
use App\Models\ReportDefinition;
use App\Models\ShiftAccount;
use App\Services\ExchangeService;
use App\Services\ShiftService;
use App\Support\SettingsRepository;
use Database\Seeders\SystemDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class ReportTypesTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
        $this->seed(SystemDefaultsSeeder::class);
    }

    private function recordTransactions(): void
    {
        $cashier = $this->createCashier();
        $syp = Currency::where('code', 'SYP')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $shift = app(ShiftService::class)->openShift($cashier, [$syp->id => '1000000']);
        ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 1000]);

        app(ExchangeService::class)->recordTransaction($shift, $cashier, [
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => '100',
            'customer_name' => 'عميل تجريبي',
        ]);
    }

    public function test_every_seeded_report_type_renders(): void
    {
        $this->recordTransactions();
        $admin = $this->createAdmin();

        foreach (ReportDefinition::all() as $definition) {
            $this->actingAs($admin)
                ->get(route('reports.show', ['report' => $definition->key, 'date_from' => '2000-01-01', 'date_to' => '2100-01-01']))
                ->assertOk();
        }
    }

    public function test_reports_index_lists_only_reports_visible_to_the_user(): void
    {
        $cashier = $this->createCashier();

        $response = $this->actingAs($cashier)->get(route('reports.index'));

        $response->assertOk();

        $keys = collect($response->viewData('page')['props']['reports'])->pluck('key');

        $this->assertTrue($keys->contains('daily-operations'));
        $this->assertFalse($keys->contains('profit-loss'), 'التقارير الخاصة بالمدير يجب ألا تظهر للكاشير.');
    }

    public function test_admin_only_report_is_forbidden_for_cashier(): void
    {
        $cashier = $this->createCashier();

        $this->actingAs($cashier)->get(route('reports.show', 'profit-loss'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.profit-loss'))->assertForbidden();
    }

    public function test_disabled_report_is_not_accessible(): void
    {
        ReportDefinition::where('key', 'shift-summary')->update(['is_enabled' => false]);

        $this->actingAs($this->createAdmin())
            ->get(route('reports.show', 'shift-summary'))
            ->assertForbidden();
    }

    public function test_unknown_report_returns_not_found(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('reports.show', 'does-not-exist'))
            ->assertNotFound();
    }

    public function test_hidden_columns_from_settings_are_removed_from_the_output(): void
    {
        ReportDefinition::where('key', 'daily-operations')
            ->update(['options' => ['hidden_columns' => ['rate', 'shift_id'], 'default_range' => null]]);

        $response = $this->actingAs($this->createAdmin())->get(route('reports.show', 'daily-operations'));

        $columns = collect($response->viewData('page')['props']['columns'])->pluck('key');

        $this->assertFalse($columns->contains('rate'));
        $this->assertFalse($columns->contains('shift_id'));
        $this->assertTrue($columns->contains('amount'));
    }

    public function test_profit_column_is_hidden_from_non_admin_users(): void
    {
        $response = $this->actingAs($this->createCashier())->get(route('reports.show', 'daily-operations'));

        $columns = collect($response->viewData('page')['props']['columns'])->pluck('key');

        $this->assertFalse($columns->contains('profit'));
    }

    public function test_csv_export_is_downloadable(): void
    {
        $this->recordTransactions();

        $response = $this->actingAs($this->createAdmin())->get(route('reports.export', [
            'report' => 'daily-operations',
            'format' => 'csv',
            'date_from' => '2000-01-01',
            'date_to' => '2100-01-01',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('عميل تجريبي', $response->streamedContent());
    }

    public function test_export_format_disabled_in_settings_is_rejected(): void
    {
        app(SettingsRepository::class)->set('reports.export_formats', ['print']);

        $this->actingAs($this->createAdmin())
            ->get(route('reports.export', ['report' => 'daily-operations', 'format' => 'csv']))
            ->assertNotFound();
    }

    public function test_print_export_renders_a_printable_page(): void
    {
        $this->recordTransactions();

        $response = $this->actingAs($this->createAdmin())->get(route('reports.export', [
            'report' => 'daily-operations',
            'format' => 'print',
            'date_from' => '2000-01-01',
            'date_to' => '2100-01-01',
        ]));

        $response->assertOk();
        $response->assertSee('العمليات اليومية', false);
    }

    public function test_default_date_range_setting_is_applied_when_no_dates_are_given(): void
    {
        app(SettingsRepository::class)->set('reports.default_range', 'this_month');

        $response = $this->actingAs($this->createAdmin())->get(route('reports.show', 'daily-operations'));

        $filters = $response->viewData('page')['props']['filters'];

        $this->assertSame(now()->startOfMonth()->toDateString(), $filters['date_from']);
        $this->assertSame(now()->toDateString(), $filters['date_to']);
    }
}
