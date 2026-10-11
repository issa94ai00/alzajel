<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * قراءة وكتابة إعدادات النظام مع تخزين مؤقت في الذاكرة لكل طلب.
 *
 * أي مفتاح غير مخزّن في جدول `settings` يعود للقيمة الافتراضية في config/sahli.php،
 * ما يعني أن النظام يعمل بشكل صحيح حتى قبل تشغيل الـ seeder.
 */
class SettingsRepository
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->all();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return $this->default($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @return array<int, mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public function default(string $key, mixed $fallback = null): mixed
    {
        // مفاتيح الإعدادات تحتوي نقاطاً (company.name)، لذلك نقرأ المصفوفة كاملة
        // بدل استخدام مسار config المتداخل.
        return config('sahli.settings', [])[$key] ?? $fallback;
    }

    /**
     * كل الإعدادات المخزّنة في قاعدة البيانات.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (! Schema::hasTable('settings')) {
            return $this->cache = [];
        }

        return $this->cache = Setting::query()
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * الإعدادات الفعّالة (الافتراضية مدموجة مع المخزّنة).
     *
     * @return array<string, mixed>
     */
    public function effective(): array
    {
        return array_merge(config('sahli.settings', []), $this->all());
    }

    /**
     * الإعدادات الفعّالة لمجموعة واحدة (بادئة المفتاح، مثل "receipts").
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return collect($this->effective())
            ->filter(fn ($value, $key) => str_starts_with($key, $group.'.'))
            ->all();
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['group' => strtok($key, '.') ?: 'general', 'value' => $value],
        );

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['group' => strtok($key, '.') ?: 'general', 'value' => $value],
            );
        }

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();

        $this->flush();
    }

    public function flush(): void
    {
        $this->cache = null;
    }
}
