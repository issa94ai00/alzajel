<?php

namespace Tests\Feature\Settings;

use App\Models\ReceiptTemplate;
use App\Models\ReportDefinition;
use App\Models\Setting;
use App\Support\SettingsRepository;
use Database\Seeders\SystemDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class ManageSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
        $this->seed(SystemDefaultsSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        $settings = app(SettingsRepository::class);

        $payload = [
            'company' => [
                'name' => 'صرافة دمشق',
                'branch' => 'الفرع الرئيسي',
                'phone' => '0999999999',
                'address' => 'دمشق',
                'tax_number' => '123',
                'logo_text' => 'DM',
            ],
            'format' => [
                'decimals' => 3,
                'thousands_separator' => true,
                'locale' => 'ar-SY',
            ],
            'reports' => [
                'default_range' => 'this_month',
                'page_size' => 25,
                'show_totals' => false,
                'export_formats' => ['csv'],
                'print_paper' => 'a5',
                'print_header' => 'ترويسة',
                'print_footer' => 'تذييل',
            ],
            'receipts' => [
                'enabled' => true,
                'auto_open_after_transaction' => true,
                'default_transaction_template' => 'thermal-58',
                'default_shift_template' => 'shift-a5',
                'footer_note' => 'شكراً',
                'show_operator' => false,
                'show_customer' => true,
                'show_notes' => true,
                'show_rate' => true,
                'show_profit' => false,
                'show_signature' => true,
                'number_prefix' => 'INV-',
            ],
            'currency' => [
                'new_lira_notice' => true,
                'old_lira_warn_above' => 100000,
            ],
        ];

        unset($settings);

        return array_replace_recursive($payload, $overrides);
    }

    public function test_cashier_cannot_open_settings(): void
    {
        $this->actingAs($this->createCashier())->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->createCashier())->put(route('settings.update'), $this->settingsPayload())->assertForbidden();
    }

    public function test_admin_can_open_settings(): void
    {
        $this->actingAs($this->createAdmin())->get(route('settings.index'))->assertOk();
    }

    public function test_admin_can_save_settings(): void
    {
        $this->actingAs($this->createAdmin())
            ->put(route('settings.update'), $this->settingsPayload())
            ->assertRedirect();

        $settings = app(SettingsRepository::class);
        $settings->flush();

        $this->assertSame('صرافة دمشق', $settings->string('company.name'));
        $this->assertSame(3, $settings->int('format.decimals'));
        $this->assertSame(['csv'], $settings->array('reports.export_formats'));
        $this->assertTrue($settings->bool('receipts.auto_open_after_transaction'));
        $this->assertTrue($settings->bool('currency.new_lira_notice'));
        $this->assertSame(100000, $settings->int('currency.old_lira_warn_above'));
        $this->assertFalse($settings->bool('receipts.show_operator'));
    }

    public function test_settings_validation_rejects_invalid_values(): void
    {
        $this->actingAs($this->createAdmin())
            ->put(route('settings.update'), $this->settingsPayload(['format' => ['decimals' => 12]]))
            ->assertSessionHasErrors('format.decimals');
    }

    public function test_group_can_be_reset_to_defaults(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload())->assertRedirect();
        $this->actingAs($admin)->post(route('settings.reset'), ['group' => 'company'])->assertRedirect();

        $settings = app(SettingsRepository::class);
        $settings->flush();

        $this->assertSame(config('sahli.settings')['company.name'], $settings->string('company.name'));
        $this->assertSame(0, Setting::where('key', 'like', 'company.%')->count());
    }

    public function test_admin_can_toggle_and_reorder_report_types(): void
    {
        $definitions = ReportDefinition::orderBy('sort_order')->get();

        $payload = ['reports' => $definitions->map(fn (ReportDefinition $definition) => [
            'id' => $definition->id,
            'name' => $definition->name,
            'description' => $definition->description,
            'is_enabled' => $definition->key !== 'customer-activity',
            'admin_only' => $definition->key === 'daily-operations',
            'sort_order' => $definition->key === 'daily-operations' ? 1 : $definition->sort_order,
            'options' => ['hidden_columns' => ['rate'], 'default_range' => 'last_30'],
        ])->all()];

        $this->actingAs($this->createAdmin())
            ->put(route('settings.reports.update'), $payload)
            ->assertRedirect();

        $daily = ReportDefinition::where('key', 'daily-operations')->firstOrFail();

        $this->assertTrue($daily->admin_only);
        $this->assertSame(1, $daily->sort_order);
        $this->assertSame(['rate'], $daily->option('hidden_columns'));
        $this->assertSame('last_30', $daily->option('default_range'));
        $this->assertFalse(ReportDefinition::where('key', 'customer-activity')->firstOrFail()->is_enabled);
    }

    public function test_admin_can_add_and_remove_a_report_variant(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('settings.reports.store'), [
            'key' => 'monthly-operations',
            'builder' => 'transactions',
            'name' => 'عمليات الشهر',
            'description' => 'كل عمليات الشهر الحالي',
            'admin_only' => false,
            'is_enabled' => true,
            'options' => ['default_range' => 'this_month'],
        ])->assertRedirect();

        $variant = ReportDefinition::where('key', 'monthly-operations')->firstOrFail();

        $this->assertSame('transactions', $variant->builder);

        $this->actingAs($admin)->get(route('reports.show', 'monthly-operations'))->assertOk();

        $this->actingAs($admin)->delete(route('settings.reports.destroy', $variant->key))->assertRedirect();

        $this->assertDatabaseMissing('report_definitions', ['key' => 'monthly-operations']);
    }

    public function test_report_variant_requires_a_known_builder(): void
    {
        $this->actingAs($this->createAdmin())->post(route('settings.reports.store'), [
            'key' => 'bad-report',
            'builder' => 'not-a-builder',
            'name' => 'تقرير',
            'admin_only' => false,
            'is_enabled' => true,
        ])->assertSessionHasErrors('builder');
    }

    public function test_admin_can_manage_receipt_templates(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('settings.receipt-templates.store'), [
            'key' => 'custom-a4',
            'name' => 'فاتورة A4',
            'subject' => 'transaction',
            'paper' => 'a4',
            'copies' => 2,
            'is_active' => true,
            'is_default' => true,
            'options' => ['title' => 'فاتورة', 'header_text' => '', 'footer_text' => '', 'show_logo' => true],
        ])->assertRedirect();

        $template = ReceiptTemplate::where('key', 'custom-a4')->firstOrFail();

        $this->assertTrue($template->is_default);
        $this->assertFalse(ReceiptTemplate::where('key', 'thermal-80')->firstOrFail()->is_default, 'قالب افتراضي واحد فقط لكل نوع.');
        $this->assertSame('custom-a4', app(SettingsRepository::class)->string('receipts.default_transaction_template'));

        $this->actingAs($admin)->put(route('settings.receipt-templates.update', $template->key), [
            'key' => 'custom-a4',
            'name' => 'فاتورة A4 معدّلة',
            'subject' => 'transaction',
            'paper' => 'a5',
            'copies' => 1,
            'is_active' => true,
            'is_default' => true,
            'options' => ['title' => 'فاتورة', 'header_text' => '', 'footer_text' => '', 'show_logo' => false],
        ])->assertRedirect();

        $this->assertSame('a5', $template->fresh()->paper);

        $this->actingAs($admin)->delete(route('settings.receipt-templates.destroy', 'custom-a4'))->assertRedirect();

        $this->assertDatabaseMissing('receipt_templates', ['key' => 'custom-a4']);
    }

    public function test_last_template_of_a_subject_cannot_be_deleted(): void
    {
        ReceiptTemplate::forSubject('shift')->where('key', '!=', 'shift-a5')->delete();

        $this->actingAs($this->createAdmin())
            ->delete(route('settings.receipt-templates.destroy', 'shift-a5'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('receipt_templates', ['key' => 'shift-a5']);
    }
}
