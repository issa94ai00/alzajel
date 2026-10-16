<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantIsolationAndLifecycleTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up any test tenants created
        $tenant = Tenant::find('democo');
        if ($tenant) {
            $tenant->delete();
        }

        parent::tearDown();
    }

    public function test_central_landing_page_is_accessible_on_central_domain(): void
    {
        $response = $this->get('http://localhost/');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'online',
        ]);
    }

    public function test_tenant_routes_are_blocked_on_central_domain(): void
    {
        // Trying to access tenant dashboard on central domain (localhost) should be aborted 404/blocked
        $response = $this->get('http://localhost/dashboard');

        $this->assertTrue(in_array($response->status(), [404, 302]));
    }

    public function test_tenant_lifecycle_creation_migrations_and_isolation(): void
    {
        // 1. Create a tenant
        $tenant = Tenant::create([
            'id' => 'democo',
            'company_name' => 'Demo Exchange Company',
        ]);
        $tenant->domains()->create(['domain' => 'democo']);

        $expectedDb = 'bashardamatechsy_tenant_democo';

        // 2. Verify database was created on MySQL
        $databases = collect(DB::select('SHOW DATABASES'))->pluck('Database')->all();
        $this->assertContains($expectedDb, $databases);

        // 3. Verify tenant database has all accounting tables
        $tenant->run(function () use ($expectedDb) {
            $currentDb = DB::connection()->getDatabaseName();
            $this->assertEquals($expectedDb, $currentDb);

            // Check that accounting tables exist
            $tables = collect(DB::select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0]);
            $this->assertTrue($tables->contains('shifts'));
            $this->assertTrue($tables->contains('exchange_transactions'));
            $this->assertTrue($tables->contains('currencies'));
            $this->assertTrue($tables->contains('roles'));

            // Check seeded data
            $this->assertGreaterThan(0, \App\Models\Currency::count());
            $this->assertGreaterThan(0, \Spatie\Permission\Models\Role::count());

            // Create a user in tenant DB
            $user = User::create([
                'name' => 'Tenant Manager',
                'email' => 'manager@democo.com',
                'password' => bcrypt('password123'),
                'status' => 'active',
            ]);
            $user->assignRole('admin');

            $this->assertDatabaseHas('users', ['email' => 'manager@democo.com']);
        });

        // 4. Verify user does NOT exist in central database
        $this->assertDatabaseMissing('users', ['email' => 'manager@democo.com']);

        // 5. Delete tenant and verify database cleanup
        $tenant->delete();

        $databasesAfter = collect(DB::select('SHOW DATABASES'))->pluck('Database')->all();
        $this->assertNotContains($expectedDb, $databasesAfter);
    }
}
