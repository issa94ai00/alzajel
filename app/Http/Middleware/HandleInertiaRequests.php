<?php

namespace App\Http\Middleware;

use App\Services\ShiftService;
use App\Support\Permissions;
use App\Support\SettingsRepository;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                // نُبقي isAdmin للتوافق مع الشاشات القديمة، لكن البوابة الحقيقية هي permissions.
                'isAdmin' => $user?->hasRole(Permissions::SUPER_ROLE) ?? false,
                'roles' => fn () => $user?->getRoleNames()->values() ?? collect(),
                'permissions' => fn () => $user?->getAllPermissions()->pluck('name')->values() ?? collect(),
                'mustChangePassword' => (bool) ($user?->must_change_password ?? false),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'receipt' => fn () => $request->session()->get('receipt'),
            ],
            'settings' => function () {
                $settings = app(SettingsRepository::class);

                return [
                    'company_name' => $settings->string('company.name', config('app.name')),
                    'decimals' => $settings->int('format.decimals', 2),
                    'thousands_separator' => $settings->bool('format.thousands_separator', true),
                    'locale' => $settings->string('format.locale', 'ar-SY'),
                    'receipts_enabled' => $settings->bool('receipts.enabled', true),
                    'receipts_auto_open' => $settings->bool('receipts.auto_open_after_transaction', false),
                    'new_lira_notice' => $settings->bool('currency.new_lira_notice', true),
                    'old_lira_warn_above' => (float) $settings->get('currency.old_lira_warn_above', 100000),
                ];
            },
            // وردية المستخدم الحالي نفسه — لكل صرّاف صندوقه المستقل.
            'currentShift' => function () use ($user) {
                if (! $user) {
                    return null;
                }

                $shift = app(ShiftService::class)->getCurrentOpenShift($user);

                return $shift ? [
                    'id' => $shift->id,
                    'business_date' => $shift->business_date->toDateString(),
                    'status' => $shift->status,
                ] : null;
            },
        ];
    }
}
