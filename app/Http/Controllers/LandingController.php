<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\SpTodayService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(Request $request, ?SpTodayService $spToday = null): Response
    {
        $plans = Plan::where('is_active', true)->get();

        $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'exchange.damatech.sy';

        $market = $this->market($request, $spToday ?? app(SpTodayService::class));

        return Inertia::render('Landing', [
            'plans' => $plans,
            'rates' => $this->legacyRates($market['rates']),
            'market' => $market,
            'centralDomain' => $centralDomain,
            'tenantCount' => max(5, Tenant::count()),
            'auth' => [
                'user' => $request->user(),
                'isAuthenticated' => (bool) $request->user(),
            ],
        ]);
    }

    /**
     * نشرة أسعار الشركة كما في صفحة /rates: العملات المفعّلة بأسعار الشراء والمبيع
     * المعتمدة في النظام، مع مرجع الليرة اليوم (sp-today) لكل عملة.
     * تُقرأ أسعار sp-today من الذاكرة المؤقتة فقط حتى لا ينتظر الزائر موقعاً خارجياً.
     */
    private function market(Request $request, SpTodayService $spToday): array
    {
        $data = $spToday->getCachedRates();
        $spRates = $data['rates'] ?? [];
        $currencies = $this->companyCurrencies($request);
        $source = 'company';

        if ($currencies->isNotEmpty()) {
            $rates = $currencies->map(function (array $currency) use ($spRates) {
                $sp = $spRates[strtoupper($currency['code'])] ?? [];
                $buy = $currency['buy'];
                $sell = $currency['sell'];

                return [
                    'code' => $currency['code'],
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'flag' => $sp['flag'] ?? '',
                    'buy' => $buy,
                    'sell' => $sell,
                    'buy_old' => round($buy * 100, 2),
                    'sell_old' => round($sell * 100, 2),
                    'market_buy' => $sp['buy_new'] ?? null,
                    'market_sell' => $sp['sell_new'] ?? null,
                    'change' => $sp['change'] ?? null,
                    'change_week' => $sp['change_week'] ?? null,
                    'change_month' => $sp['change_month'] ?? null,
                    'featured' => true,
                ];
            });
        } else {
            // لا توجد شركة مرتبطة بهذا النطاق أو لا أسعار معتمدة بعد: نعرض نشرة sp-today كما هي.
            $source = 'sp-today';
            $featured = ['USD', 'EUR', 'SAR', 'AED', 'TRY', 'GBP', 'JOD', 'KWD'];
            $rates = collect($spRates)
                ->sortBy(fn ($rate) => ($i = array_search($rate['code'], $featured, true)) === false ? 100 : $i)
                ->map(fn ($rate) => [
                    'code' => $rate['code'],
                    'name' => $rate['name_ar'],
                    'symbol' => SpTodayService::SYMBOLS[$rate['code']] ?? $rate['code'],
                    'flag' => $rate['flag'],
                    'buy' => $rate['buy_new'],
                    'sell' => $rate['sell_new'],
                    'buy_old' => $rate['buy_traditional'],
                    'sell_old' => $rate['sell_traditional'],
                    'market_buy' => $rate['buy_new'],
                    'market_sell' => $rate['sell_new'],
                    'change' => $rate['change'] ?? null,
                    'change_week' => $rate['change_week'] ?? null,
                    'change_month' => $rate['change_month'] ?? null,
                    'featured' => in_array($rate['code'], $featured, true),
                ]);
        }

        $rates = $rates->values();

        return [
            'available' => $rates->isNotEmpty(),
            'source' => $source,
            'updated_at' => $currencies->max('updated_at') ?? ($data['updated_at'] ?? null),
            'market_updated_at' => $data['updated_at'] ?? null,
            'fetched_at' => $data['fetched_at'] ?? null,
            'rates' => $rates,
            'gold' => $data['gold'] ?? null,
        ];
    }

    /**
     * Active, priced currencies of the exchange that owns this domain — the same
     * set ExchangeRateController::index lists. On a tenant domain tenancy is
     * already initialised; on exchange.damatech.sy the tenant is found through
     * the central domains table.
     */
    private function companyCurrencies(Request $request): Collection
    {
        // Flatten inside the tenant context: casting dates later needs the tenant
        // connection, which is gone once Tenant::run() returns.
        $load = fn () => Currency::with('exchangeRate')
            ->where('is_active', true)
            ->where('is_base', false)
            ->orderBy('id')
            ->get()
            ->filter(fn (Currency $c) => $c->exchangeRate && (float) $c->exchangeRate->buy_rate > 0 && (float) $c->exchangeRate->sell_rate > 0)
            ->map(fn (Currency $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
                'buy' => (float) $c->exchangeRate->buy_rate,
                'sell' => (float) $c->exchangeRate->sell_rate,
                'updated_at' => $c->exchangeRate->updated_at?->toIso8601String(),
            ])
            ->values();

        if (tenancy()->initialized) {
            return $load();
        }

        $tenantId = DB::connection(config('tenancy.database.central_connection'))
            ->table('domains')
            ->where('domain', $request->getHost())
            ->value('tenant_id');

        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        return $tenant ? $tenant->run($load) : collect();
    }

    /**
     * The shape the currently deployed Landing bundle reads (prop `rates`).
     * `change` there is a display string; it carries sp-today's daily move.
     */
    private function legacyRates(Collection $rates): array
    {
        return $rates->where('featured', true)->take(8)->map(fn ($rate) => [
            'code' => $rate['code'],
            'name' => $rate['name'],
            'symbol' => $rate['symbol'] ?: $rate['code'],
            'flag' => $rate['flag'],
            'buy' => $rate['buy'],
            'sell' => $rate['sell'],
            'change' => $rate['change'] === null ? '' : sprintf('%+.2f%%', $rate['change']),
            'trend' => ($rate['change'] ?? 0) < 0 ? 'down' : 'up',
        ])->values()->all();
    }
}
