<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\CustomResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, CustomResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, CustomResetPassword::class, function ($notification) use ($user) {
            $response = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email));

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_expired_reset_password_link_redirects_to_request_screen(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, CustomResetPassword::class, function ($notification) use ($user) {
            DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->update(['created_at' => now()->subMinutes(61)]);

            $response = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email));

            $response
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('status', '링크가 만료되었습니다. 다시 요청해 주세요.');

            return true;
        });
    }

    public function test_invalid_reset_password_link_redirects_to_request_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/reset-password/invalid-token?email='.urlencode($user->email));

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', '링크가 만료되었습니다. 다시 요청해 주세요.');
    }

    public function test_reset_password_link_without_email_redirects_to_request_screen(): void
    {
        $response = $this->get('/reset-password/some-token');

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', '링크가 만료되었습니다. 다시 요청해 주세요.');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, CustomResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
