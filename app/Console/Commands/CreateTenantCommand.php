<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create 
                            {subdomain : The subdomain for the tenant (e.g. albaraka)}
                            {company_name : The display company name}
                            {admin_name : The administrator full name}
                            {admin_email : The administrator email address}
                            {--password= : The administrator password (default: password123)}';

    protected $description = 'Create a new tenant with its isolated database, run migrations, seed data, and create the initial admin user';

    public function handle(): int
    {
        $subdomain = strtolower($this->argument('subdomain'));
        $companyName = $this->argument('company_name');
        $adminName = $this->argument('admin_name');
        $adminEmail = $this->argument('admin_email');
        $password = $this->option('password') ?: 'password123';

        if (Tenant::find($subdomain)) {
            $this->error("Tenant [{$subdomain}] already exists!");
            return self::FAILURE;
        }

        $this->info("Creating tenant [{$subdomain}] - [{$companyName}]...");

        // 1. Create tenant (fires TenantCreated -> creates DB, runs tenant migrations, runs TenantDatabaseSeeder)
        $tenant = Tenant::create([
            'id' => $subdomain,
            'company_name' => $companyName,
            'status' => 'active',
        ]);

        // 2. Attach domain
        $tenant->domains()->create(['domain' => $subdomain]);
        $this->info("Database and domains configured successfully.");

        // 3. Create initial admin user inside the tenant's isolated database
        $this->info("Creating initial admin user inside tenant database...");
        $tenant->run(function () use ($adminName, $adminEmail, $password) {
            $user = User::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($password),
                'status' => 'active',
            ]);

            $user->assignRole('admin');
        });

        $centralDomain = config('tenancy.central_domains')[2] ?? 'bashar.damatech.sy';
        $url = "https://{$subdomain}.{$centralDomain}/login";

        $this->newLine();
        $this->info("==================================================");
        $this->info("Tenant created successfully!");
        $this->info("Company:     {$companyName}");
        $this->info("Subdomain:   {$subdomain}");
        $this->info("Admin Email: {$adminEmail}");
        $this->info("Password:    {$password}");
        $this->info("Login URL:   {$url}");
        $this->info("==================================================");

        return self::SUCCESS;
    }
}
