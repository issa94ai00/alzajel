<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * يقطع وصول الحساب فور إيقافه حتى لو كانت جلسته مفتوحة من قبل.
 */
class EnsureUserIsActive
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $reason = $user->loginBlockReason();

        if (! $reason) {
            return $next($request);
        }

        $this->activity->log(
            ActivityLogger::AUTH_BLOCKED,
            "إنهاء جلسة الحساب {$user->email} — الحالة: {$user->statusLabel()}",
            $user,
            ['status' => $user->status],
            $user,
        );

        if ($request->expectsJson()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => $reason], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $reason]);
    }
}
