<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function login(Request $request): array
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->activity->log(
                ActivityLogger::AUTH_FAILED,
                'محاولة دخول فاشلة من التطبيق بالبريد '.$credentials['email'],
                $user,
                ['email' => $credentials['email'], 'channel' => 'api'],
                $user,
            );

            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        if ($reason = $user->loginBlockReason()) {
            $this->activity->log(
                ActivityLogger::AUTH_BLOCKED,
                "محاولة دخول مرفوضة من التطبيق للحساب {$user->email} — الحالة: {$user->statusLabel()}",
                $user,
                ['status' => $user->status, 'channel' => 'api'],
                $user,
            );

            throw ValidationException::withMessages([
                'email' => [$reason],
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->activity->log(
            ActivityLogger::AUTH_LOGIN,
            "تسجيل دخول من التطبيق للمستخدم {$user->name}",
            $user,
            ['channel' => 'api', 'device' => $credentials['device_name']],
            $user,
        );

        return [
            'token' => $user->createToken($credentials['device_name'])->plainTextToken,
            'user' => new UserResource($user),
        ];
    }

    public function logout(Request $request): array
    {
        $user = $request->user();

        $this->activity->log(
            ActivityLogger::AUTH_LOGOUT,
            "تسجيل خروج من التطبيق للمستخدم {$user->name}",
            $user,
            ['channel' => 'api'],
            $user,
        );

        $user->currentAccessToken()->delete();

        return ['message' => 'تم تسجيل الخروج بنجاح.'];
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
