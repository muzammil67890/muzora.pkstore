<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_login_uses_the_separate_admin_guard(): void
    {
        $admin = $this->makeAdmin();

        $this->post('/admin/login', [
            'email' => 'ADMIN@example.com',
            'password' => 'AdminSecurePass123!',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
        $this->assertTrue(Hash::check('AdminSecurePass123!', $admin->password));
    }

    public function test_invalid_admin_login_is_rejected(): void
    {
        $this->makeAdmin();

        $this->from('/admin/login')->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'not-the-password',
        ])->assertRedirect('/admin/login')->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $admin = $this->makeAdmin();
        $admin->forceFill(['is_active' => false])->save();

        $this->from('/admin/login')->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'AdminSecurePass123!',
        ])->assertRedirect('/admin/login')->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_unauthenticated_admin_routes_redirect_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/products')->assertRedirect(route('admin.login'));
        $this->get('/admin/categories')->assertRedirect(route('admin.login'));
        $this->get('/admin/brands')->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_admin_can_access_dashboard_and_catalog_foundation(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Total products')
            ->assertSee('Total customers')
            ->assertDontSee('Total sales');

        $this->get('/admin/products')->assertOk()->assertSee('Products');
        $this->get('/admin/categories')->assertOk()->assertSee('Categories');
        $this->get('/admin/brands')->assertOk()->assertSee('Brands');
    }

    public function test_customer_authentication_does_not_grant_admin_access(): void
    {
        $customer = User::create([
            'name' => 'Regular Customer',
            'email' => 'customer@example.com',
            'phone' => '03001234000',
            'password' => 'CustomerSecure123',
        ]);

        $this->actingAs($customer, 'web')->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertAuthenticatedAs($customer, 'web');
        $this->assertGuest('admin');
    }

    public function test_active_admin_is_logged_out_if_deactivated_during_an_existing_session(): void
    {
        $admin = $this->makeAdmin();
        $admin->forceFill(['is_active' => false])->save();

        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Store Admin',
            'email' => 'admin@example.com',
            'password' => 'AdminSecurePass123!',
        ]);
    }
}
