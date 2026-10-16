<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TenantRegistrationController extends Controller
{
    public function create()
    {
        return \Inertia\Inertia::render('Auth/RegisterTenant', [
            'centralDomain' => (parse_url(config('app.url'), PHP_URL_HOST) ?: 'bashar.damatech.sy'),
        ]);
    }

    public function plans()
    {
        $plans = Plan::where('is_active', true)->get();
        return response()->json($plans);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'subdomain'    => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                'unique:domains,domain',
                Rule::notIn(['www', 'admin', 'root', 'api', 'central', 'mail', 'app']),
            ],
            'admin_name'   => ['required', 'string', 'max:255'],
            'admin_email'  => ['required', 'string', 'email', 'max:255'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'plan_id'      => ['nullable', 'exists:plans,id'],
        ]);

        $subdomain = strtolower($validated['subdomain']);

        // 1. إنشاء المستأجر في القاعدة المركزية (ينشئ الـ DB ويطبق الهجرات والـ Seeders تلقائياً)
        $tenant = Tenant::create([
            'id'           => $subdomain,
            'company_name' => $validated['company_name'],
            'plan_id'      => $validated['plan_id'] ?? null,
            'status'       => 'active',
        ]);

        // 2. تسجيل النطاق الفرعي
        $tenant->domains()->create([
            'domain' => $subdomain,
        ]);

        // 3. إنشاء المستخدم المدير الأول داخل قاعدة بيانات الشركة
        $tenant->run(function () use ($validated) {
            $user = User::create([
                'name'     => $validated['admin_name'],
                'email'    => $validated['admin_email'],
                'password' => Hash::make($validated['password']),
                'status'   => 'active',
            ]);

            // إسناد دور admin الذي تم زرعه في الـ Seeder
            $user->assignRole('admin');
        });

        $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'bashar.damatech.sy';
        $scheme = request()->isSecure() ? 'https://' : 'http://';
        $tenantUrl = "{$scheme}{$subdomain}.{$centralDomain}";

        if ($request->header('X-Inertia')) {
            return \Inertia\Inertia::render('Auth/RegisterTenant', [
                'centralDomain' => $centralDomain,
                'createdTenant' => [
                    'company_name' => $tenant->company_name,
                    'subdomain'    => $subdomain,
                    'admin_email'  => $validated['admin_email'],
                    'login_url'    => "{$tenantUrl}/login",
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء حساب الشركة وقاعدة البيانات بنجاح!',
            'tenant'  => [
                'id'           => $tenant->id,
                'company_name' => $tenant->company_name,
                'subdomain'    => $subdomain,
                'login_url'    => "{$tenantUrl}/login",
            ],
        ], 201);
    }
}
