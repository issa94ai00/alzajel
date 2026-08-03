<?php

namespace App\Http\Middleware;

use App\Services\ShiftService;
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
                'isAdmin' => $user?->hasRole('admin') ?? false,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'currentShift' => function () use ($user) {
                if (! $user) {
                    return null;
                }

                $shift = app(ShiftService::class)->getCurrentOpenShift();

                return $shift ? [
                    'id' => $shift->id,
                    'business_date' => $shift->business_date->toDateString(),
                    'status' => $shift->status,
                ] : null;
            },
        ];
    }
}
