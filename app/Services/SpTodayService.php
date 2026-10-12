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

    /**
     * جلب أسعار الصرف من موقع الليرة اليوم (sp-today.com)
     *
     * @param bool $forceRefresh
     * @return array
     */
    public function getRates(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && Cache::has(self::CACHE_KEY)) {
            $cached = Cache::get(self::CACHE_KEY);
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
                        'is_fallback' => false,
                    ];

                    Cache::put(self::CACHE_KEY, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));
                    Cache::forever(self::CACHE_FALLBACK_KEY, $result);

                    return $result;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch rates from sp-today.com: ' . $e->getMessage());
        }

        // في حال فشل الجلب، نستخدم آخر نسخة محفوظة صالحة
        if (Cache::has(self::CACHE_FALLBACK_KEY)) {
            $fallback = Cache::get(self::CACHE_FALLBACK_KEY);
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
     * استخراج بيانات الأسعار والعملات من صفحة Next.js
     *
     * @param string $html
     * @return array
     */
    private function parseHtml(string $html): array
    {
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
}
