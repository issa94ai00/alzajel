<?php

namespace Tests\Feature\Reports;

use App\Models\Currency;
use App\Models\ShiftAccount;
use App\Services\ExchangeService;
use App\Services\ReportService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class ProfitLossReportTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    public function test_shift_total_profit_equals_sum_of_transaction_profits(): void
    {
        $cashier = $this->createCashier();
        $syp = Currency::where('code', 'SYP')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $shiftService = app(ShiftService::class);
        $exchangeService = app(ExchangeService::class);

        $shift = $shiftService->openShift($cashier, [$syp->id => '1000000']);
        ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 1000]);

        // Sell at reference rate: profit = 100 * (105 - 100) = 500
        $exchangeService->recordTransaction($shift, $cashier, [
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => '100',
        ]);

        // Buy with an override rate of 98 instead of 100: profit = 100*(105-98) = 700
        $exchangeService->recordTransaction($shift, $cashier, [
            'currency_id' => $usd->id,
            'type' => 'buy',
            'amount' => '100',
            'rate' => '98',
        ]);

        $report = app(ReportService::class)->profitLossForShift($shift->fresh());

        $this->assertSame('1200.0000', $report['total_profit']);
        $this->assertSame(2, $report['transaction_count']);
    }

    public function test_profit_loss_route_is_admin_only(): void
    {
        $cashier = $this->createCashier();

        $response = $this->actingAs($cashier)->get(route('reports.profit-loss'));

        $response->assertForbidden();
    }
}
