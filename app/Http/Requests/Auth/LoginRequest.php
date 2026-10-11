<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $logger = app(ActivityLogger::class);

        // الحسابات الموقوفة أو غير المفعّلة تُرفض قبل محاولة المصادقة أصلاً،
        // حتى لا تُنشأ لها جلسة ثم تُبطَل.
        $candidate = User::where('email', $this->string('email'))->first();

        if ($candidate && ($reason = $candidate->loginBlockReason())) {
            RateLimiter::hit($this->throttleKey());

            $logger->log(
                ActivityLogger::AUTH_BLOCKED,
                "محاولة دخول مرفوضة للحساب {$candidate->email} — الحالة: {$candidate->statusLabel()}",
                $candidate,
                ['status' => $candidate->status],
                $candidate,
            );

            throw ValidationException::withMessages([
                'email' => $reason,
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            $logger->log(
                ActivityLogger::AUTH_FAILED,
                'محاولة دخول فاشلة بالبريد '.$this->string('email'),
                $candidate,
                ['email' => (string) $this->string('email')],
                $candidate,
            );

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = Auth::user();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->ip(),
        ])->save();

        $logger->log(
            ActivityLogger::AUTH_LOGIN,
            "تسجيل دخول للمستخدم {$user->name}",
            $user,
            [],
            $user,
        );
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
