<?php

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_cashier_is_forbidden_from_admin_only_routes(): void
    {
        $cashier = $this->createCashier();

        $this->actingAs($cashier)->get(route('rates.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('users.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.profit-loss'))->assertForbidden();
    }

    public function test_admin_can_access_admin_only_routes(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('rates.index'))->assertOk();
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->actingAs($admin)->get(route('reports.profit-loss'))->assertOk();
    }

    public function test_cashier_can_access_shared_routes(): void
    {
        $cashier = $this->createCashier();

        $this->actingAs($cashier)->get(route('dashboard'))->assertOk();
        $this->actingAs($cashier)->get(route('shifts.create'))->assertOk();
        $this->actingAs($cashier)->get(route('reports.balances'))->assertOk();
    }
}
