<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpTodayService
{
    private const CACHE_KEY = 'sp_today_rates';
    private const CACHE_FALLBACK_KEY = 'sp_today_rates_fallback';
    private const CACHE_TTL_MINUTES = 10;
    private const SP_TODAY_URL = 'https://sp-today.com';

    /** رموز العرض للعملات المستوردة من الليرة اليوم. */
    public const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'TRY' => '₺',
        'SAR' => 'ر.س',
        'AED' => 'د.إ',
        'GBP' => '£',
        'JOD' => 'د.أ',
        'KWD' => 'د.ك',
        'QAR' => 'ر.ق',
        'BHD' => 'د.ب',
        'OMR' => 'ر.ع',
        'EGP' => 'ج.م',
        'LYD' => 'د.ل',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'CHF' => 'CHF',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'DKK' => 'kr',
        'RUB' => '₽',
        'IQD' => 'د.ع',
        'SYP' => 'ل.س',
        'DZD' => 'د.ج',
        'MAD' => 'د.م',
        'TND' => 'د.ت',
        'MYR' => 'RM',
        'BRL' => 'R$',
        'NZD' => 'NZ$',
        'ZAR' => 'R',
        'IRR' => '﷼',
        'SGD' => 'S$',
    ];

    /**
     * جلب أسعار الصرف من موقع الليرة اليوم (sp-today.com)
     *
     * @param bool $forceRefresh
     * @return array
     */
    public function getRates(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && $this->cache()->has(self::CACHE_KEY)) {
            $cached = $this->cache()->get(self::CACHE_KEY);
            if (is_array($cached) && !empty($cached['rates'])) {
                return $cached;
            }
        }

        try {
            $response = Http::timeout(8)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                ->get(self::SP_TODAY_URL);

            if ($response->successful()) {
                $html = $response->body();
                $parsed = $this->parseHtml($html);

                if (!empty($parsed['rates'])) {
                    $result = [
                        'success' => true,
                        'source' => 'sp-today.com',
                        'city' => 'damascus',
                        'city_name' => 'دمشق (السوق الموازي)',
                        'updated_at' => $parsed['updated_at'] ?? now()->toIso8601String(),
                        'fetched_at' => now()->toIso8601String(),
                        'rates' => $parsed['rates'],
                        'gold' => $parsed['gold'] ?? null,
                        'is_fallback' => false,
                    ];

                    $this->cache()->put(self::CACHE_KEY, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));
                    $this->cache()->forever(self::CACHE_FALLBACK_KEY, $result);

                    return $result;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch rates from sp-today.com: ' . $e->getMessage());
        }

        // في حال فشل الجلب، نستخدم آخر نسخة محفوظة صالحة
        if ($this->cache()->has(self::CACHE_FALLBACK_KEY)) {
            $fallback = $this->cache()->get(self::CACHE_FALLBACK_KEY);
            if (is_array($fallback)) {
                $fallback['is_fallback'] = true;
                $fallback['error'] = 'تعذر الاتصال المباشر بـ sp-today، تم استخدام آخر بيانات محفوظة.';
                return $fallback;
            }
        }

        return [
            'success' => false,
            'source' => 'sp-today.com',
            'city' => 'damascus',
            'city_name' => 'دمشق',
            'updated_at' => null,
            'fetched_at' => now()->toIso8601String(),
            'rates' => [],
            'is_fallback' => false,
            'error' => 'تعذر جلب أسعار الصرف من sp-today.com حالياً.',
        ];
    }

    /**
     * Under tenancy the Cache facade tags every call, which the database store
     * rejects ("does not support tagging"). The store itself is already per-tenant
     * because the tenant's DB connection is the default, so use it directly.
     */
    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store();
    }

    /**
     * آخر أسعار محفوظة دون انتظار sp-today — للصفحات العامة.
     * لا يتصل بالمصدر إلا إن لم تكن هناك أي نسخة محفوظة؛ التحديث الدوري يتم عبر المجدول.
     */
    public function getCachedRates(): array
    {
        foreach ([self::CACHE_KEY, self::CACHE_FALLBACK_KEY] as $key) {
            $cached = $this->cache()->get($key);
            if (is_array($cached) && ! empty($cached['rates'])) {
                $cached['is_fallback'] = false;
                unset($cached['error']);

                return $cached;
            }
        }

        return $this->getRates();
    }

    /**
     * استخراج بيانات الأسعار والعملات من صفحة Next.js
     *
     * @param string $html
     * @return array
     */
    private function parseHtml(string $html): array
    {
        $structured = $this->parsePayload($html);
        if (! empty($structured['rates'])) {
            return $structured;
        }

        $rates = [];
        $updatedAt = null;

        // استخراج تاريخ آخر تحديث من الصفحة
        if (preg_match('/\\\\"updated_at\\\\":\\\\"([^\\\\"]+)\\\\"/', $html, $dateMatch)) {
            $updatedAt = $dateMatch[1];
        }

        // استخراج أسعار العملات لسوق دمشق
        $pattern = '/\\\\"code\\\\":\\\\"([A-Z]{3})\\\\".*?\\\\"name_ar\\\\":\\\\"([^\\\\"]*)\\\\".*?\\\\"flag\\\\":\\\\"([^\\\\"]*)\\\\".*?\\\\"cities\\\\":\{.*?\\\\"damascus\\\\":\{.*?\\\\"buy\\\\":([0-9.]+),.*?\\\\"sell\\\\":([0-9.]+)/s';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $code = strtoupper($m[1]);
                if (isset($rates[$code])) {
                    continue;
                }

                $buyTrad = (float) $m[4];
                $sellTrad = (float) $m[5];

                // التحويل إلى الليرة السورية الجديدة (حذف صفرين: 100 ليرة قديمة = 1 ليرة جديدة)
                $buyNew = round($buyTrad / 100, 4);
                $sellNew = round($sellTrad / 100, 4);

                $rates[$code] = [
                    'code' => $code,
                    'name_ar' => stripslashes($m[2]),
                    'flag' => $m[3],
                    'buy_traditional' => $buyTrad,
                    'sell_traditional' => $sellTrad,
                    'buy_new' => $buyNew,
                    'sell_new' => $sellNew,
                    'spread_traditional' => round($sellTrad - $buyTrad, 2),
                    'spread_new' => round($sellNew - $buyNew, 4),
                ];
            }
        }

        return [
            'rates' => $rates,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * قراءة بيانات Next.js المضمّنة (self.__next_f) كـ JSON بدلاً من التعابير النمطية،
     * لنحصل على التغير اليومي/الأسبوعي/الشهري ونطاق اليوم وأسعار الذهب.
     */
    private function parsePayload(string $html): array
    {
        $payload = '';
        $marker = 'self.__next_f.push([1,';
        $offset = 0;

        while (($start = strpos($html, $marker, $offset)) !== false) {
            $start += strlen($marker);
            $end = strpos($html, '])</script>', $start);
            if ($end === false) {
                break;
            }

            $chunk = json_decode(substr($html, $start, $end - $start));
            if (is_string($chunk)) {
                $payload .= $chunk;
            }
            $offset = $end;
        }

        $list = $this->extractJson($payload, 'allCurrencies') ?? $this->extractJson($payload, 'rates') ?? [];
        $rates = [];
        $updatedAt = null;

        foreach ($list as $item) {
            $city = $item['cities']['damascus'] ?? null;
            $code = strtoupper((string) ($item['code'] ?? ''));

            if (! $city || strlen($code) !== 3 || isset($rates[$code]) || empty($city['buy']) || empty($city['sell'])) {
                continue;
            }

            $buyTrad = (float) $city['buy'];
            $sellTrad = (float) $city['sell'];
            $updatedAt ??= $item['updated_at'] ?? null;

            $rates[$code] = [
                'code' => $code,
                'name_ar' => (string) ($item['name_ar'] ?? $item['name'] ?? $code),
                'flag' => (string) ($item['flag'] ?? ''),
                'buy_traditional' => $buyTrad,
                'sell_traditional' => $sellTrad,
                'buy_new' => round($buyTrad / 100, 4),
                'sell_new' => round($sellTrad / 100, 4),
                'spread_traditional' => round($sellTrad - $buyTrad, 2),
                'spread_new' => round(($sellTrad - $buyTrad) / 100, 4),
                'change' => $this->percent($city['change'] ?? null),
                'change_week' => $this->percent($city['change_week'] ?? null),
                'change_month' => $this->percent($city['change_month'] ?? null),
                'prev_close_new' => isset($city['prev_close']) ? round((float) $city['prev_close'] / 100, 4) : null,
                'day_high_new' => isset($city['day_high']) ? round((float) $city['day_high'] / 100, 4) : null,
                'day_low_new' => isset($city['day_low']) ? round((float) $city['day_low'] / 100, 4) : null,
            ];
        }

        return [
            'rates' => $rates,
            'updated_at' => $updatedAt,
            'gold' => $this->parseGold($this->extractJson($payload, 'gold')),
        ];
    }

    private function parseGold(?array $gold): ?array
    {
        if (! $gold) {
            return null;
        }

        $karats = [];
        foreach ($gold['karats'] ?? [] as $karat) {
            $city = $karat['cities']['damascus'] ?? null;
            if (! $city || empty($city['buy'])) {
                continue;
            }

            $karats[] = [
                'karat' => (string) $karat['karat'],
                'buy_new' => round((float) $city['buy'] / 100, 2),
                'sell_new' => round((float) ($city['sell'] ?? $city['buy']) / 100, 2),
                'change' => $this->percent($city['change'] ?? null),
            ];
        }

        $ounce = $gold['ounce'] ?? null;

        return [
            'karats' => $karats,
            'ounce_usd' => isset($ounce['price_usd']) ? (float) $ounce['price_usd'] : null,
            'ounce_change' => $this->percent($ounce['change'] ?? null),
            'updated_at' => $ounce['updated_at'] ?? null,
        ];
    }

    private function percent(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * يقتطع أول قيمة JSON (مصفوفة أو كائن) تلي المفتاح المعطى ويفك ترميزها.
     */
    private function extractJson(string $source, string $key): ?array
    {
        $pos = strpos($source, '"'.$key.'":');
        if ($pos === false) {
            return null;
        }

        $start = $pos + strlen($key) + 3;
        $open = $source[$start] ?? '';
        if ($open !== '[' && $open !== '{') {
            return null;
        }

        $close = $open === '[' ? ']' : '}';
        $depth = 0;
        $inString = false;

        for ($i = $start, $len = strlen($source); $i < $len; $i++) {
            $char = $source[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === $open) {
                $depth++;
            } elseif ($char === $close && --$depth === 0) {
                $decoded = json_decode(substr($source, $start, $i - $start + 1), true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }
}
