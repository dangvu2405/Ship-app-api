<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reset_password_with_emailed_otp(): void
    {
        Mail::fake();
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);

        $user = User::factory()->create([
            'email' => 'recover@ceta.test',
            'password' => Hash::make('OldPass123'),
            'status' => 'active',
        ]);

        $forgotResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $forgotResponse->assertOk()
            ->assertJsonPath('success', true);

        $otp = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user, &$otp): bool {
            $otp = $mail->otp;

            return $mail->hasTo($user->email)
                && preg_match('/^\d{6}$/', $mail->otp) === 1;
        });

        $checkResponse = $this->postJson('/api/auth/check-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ]);

        $checkResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['otp_token']]);

        $otpToken = $checkResponse->json('data.otp_token');
        $newPassword = 'NewPass123456X';

        $resetResponse = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'otp_token' => $otpToken,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $resetResponse->assertOk()
            ->assertJsonPath('success', true);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $newPassword,
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'refreshToken']]);
    }
}
