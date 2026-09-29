<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_customer_registration_creates_a_hashed_password_and_logs_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ayesha Customer',
            'email' => 'AYESHA@example.com',
            'phone' => '03001234567',
            'is_admin' => true,
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $user = User::where('email', 'ayesha@example.com')->firstOrFail();
        $response->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertTrue(Hash::check('StrongPass123', $user->password));
        $this->assertNotSame('StrongPass123', $user->password);
        $this->assertSame('03001234567', $user->phone);
        $this->assertNull($user->getAttribute('is_admin'));
    }

    public function test_duplicate_customer_email_is_rejected(): void
    {
        User::create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
            'phone' => '03005554444',
            'password' => 'ExistingPass123',
        ]);

        $this->from('/register')->post('/register', [
            'name' => 'Another Customer',
            'email' => 'existing@example.com',
            'phone' => '03005554444',
            'password' => 'AnotherPass123',
            'password_confirmation' => 'AnotherPass123',
        ])->assertSessionHasErrors('email');
    }

    public function test_customer_login_accepts_valid_credentials_and_rejects_invalid_credentials(): void
    {
        $user = User::create([
            'name' => 'Login Customer',
            'email' => 'login@example.com',
            'phone' => '03001112222',
            'password' => 'CustomerPass123',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'LOGIN@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest('web');

        $this->post('/login', [
            'email' => 'LOGIN@example.com',
            'password' => 'CustomerPass123',
        ])->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_customer_logout_clears_the_authenticated_session(): void
    {
        $user = User::create([
            'name' => 'Logout Customer',
            'email' => 'logout@example.com',
            'phone' => '03001112223',
            'password' => 'CustomerPass123',
        ]);

        $this->actingAs($user, 'web')->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest('web');
    }

    public function test_customer_account_routes_require_authentication_and_profile_is_available(): void
    {
        $this->get('/account')->assertRedirect(route('login'));

        $user = User::create([
            'name' => 'Profile Customer',
            'email' => 'profile@example.com',
            'phone' => '03001112224',
            'password' => 'CustomerPass123',
        ]);

        $this->actingAs($user, 'web')
            ->get('/account/profile')
            ->assertOk()
            ->assertSee('profile@example.com');
    }

    public function test_customer_can_update_profile_and_duplicate_email_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Profile Customer',
            'email' => 'profile@example.com',
            'phone' => '03001112224',
            'password' => 'CustomerPass123',
        ]);
        $other = User::create([
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'phone' => '03001112225',
            'password' => 'CustomerPass123',
        ]);

        $this->actingAs($user, 'web')->put('/account/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '03009998888',
            'address' => 'Karachi, Sindh',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '03009998888',
            'address' => 'Karachi, Sindh',
        ]);

        $this->from('/account/profile')->put('/account/profile', [
            'name' => 'Updated Name',
            'email' => $other->email,
            'phone' => '03009998888',
            'address' => 'Karachi',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::create([
            'name' => 'Password Customer',
            'email' => 'password@example.com',
            'phone' => '03001112226',
            'password' => 'CurrentPass123',
        ]);

        $this->actingAs($user, 'web')->from('/account/password')->put('/account/password', [
            'current_password' => 'incorrect',
            'password' => 'NewSecurePass456',
            'password_confirmation' => 'NewSecurePass456',
        ])->assertSessionHasErrors('current_password');

        $this->put('/account/password', [
            'current_password' => 'CurrentPass123',
            'password' => 'NewSecurePass456',
            'password_confirmation' => 'NewSecurePass456',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewSecurePass456', $user->fresh()->password));
    }

    public function test_customer_can_request_and_complete_password_reset(): void
    {
        Notification::fake();
        $user = User::create([
            'name' => 'Reset Customer',
            'email' => 'reset@example.com',
            'phone' => '03001112227',
            'password' => 'CurrentPass123',
        ]);

        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;
            return true;
        });
        $this->assertNotNull($token);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ReplacementPass456',
            'password_confirmation' => 'ReplacementPass456',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('ReplacementPass456', $user->fresh()->password));
    }
}
