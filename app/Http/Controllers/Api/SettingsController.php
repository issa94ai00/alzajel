<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SettingsRepository;

/**
 * الإعدادات التي يحتاجها التطبيق المحمول للعرض والطباعة.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): array
    {
        return collect(['company', 'format', 'receipts', 'reports'])
            ->mapWithKeys(fn (string $group) => [$group => $this->withoutPrefix($group)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function withoutPrefix(string $group): array
    {
        return collect($this->settings->group($group))
            ->mapWithKeys(fn ($value, $key) => [substr($key, strlen($group) + 1) => $value])
            ->all();
    }
}
