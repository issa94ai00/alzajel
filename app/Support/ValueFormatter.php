<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * تنسيق قيم التقارير للطباعة والتصدير حسب إعدادات العرض.
 */
class ValueFormatter
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function format(mixed $value, string $format = 'text'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($format) {
            'number', 'signed' => $this->number($value),
            'integer' => number_format((float) $value, 0, '.', $this->separator()),
            'date' => $this->date($value, 'Y-m-d'),
            'datetime' => $this->date($value, 'Y-m-d H:i'),
            'type' => $value === 'buy' ? 'شراء' : ($value === 'sell' ? 'بيع' : (string) $value),
            default => (string) $value,
        };
    }

    public function number(mixed $value): string
    {
        $decimals = max(0, min(6, $this->settings->int('format.decimals', 2)));

        return number_format((float) $value, $decimals, '.', $this->separator());
    }

    private function date(mixed $value, string $format): string
    {
        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function separator(): string
    {
        return $this->settings->bool('format.thousands_separator', true) ? ',' : '';
    }
}
