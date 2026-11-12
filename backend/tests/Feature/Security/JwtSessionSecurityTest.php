<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtSessionSecurityTest extends SecurityTestCase
{
    private function makeUser(string $email): User
    {
        $user = User::create([
            'first_name' => 'Jwt',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('Secret123!'),
        ]);

        $user->roles()->attach(Role::firstOrCreate(['name' => 'ciudadano'])->id);

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    public function test_logout_invalida_el_token_actual(): void
    {
        $user = $this->makeUser('logout@test.local');
        $token = $this->tokenFor($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/auth/profile')
            ->assertStatus(401);
    }

    public function test_refresh_emite_un_token_nuevo_y_valido(): void
    {
        $user = $this->makeUser('refresh@test.local');
        $token = $this->tokenFor($user);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/auth/refresh')
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_in']);

        $newToken = $response->json('token');
        $this->assertNotSame($token, $newToken);

        $this->withHeaders(['Authorization' => "Bearer {$newToken}"])
            ->getJson('/api/auth/profile')
            ->assertOk();
    }

    public function test_cambio_de_contrasena_revoca_tokens_anteriores(): void
    {
        $user = $this->makeUser('change-password@test.local');
        $token = $this->tokenFor($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson('/api/auth/change-password', [
                'currentPassword' => 'Secret123!',
                'newPassword' => 'NewSecret123!',
            ])
            ->assertOk();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/auth/profile')
            ->assertStatus(401);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'NewSecret123!',
        ])->assertOk();
    }

    public function test_restablecimiento_de_contrasena_revoca_tokens_anteriores(): void
    {
        $rawResetToken = 'reset-token-for-jwt-test';
        $user = $this->makeUser('reset-jwt@test.local');
        $user->forceFill([
            'reset_token' => hash('sha256', $rawResetToken),
            'reset_token_expires' => now()->addHour(),
        ])->save();

        $token = $this->tokenFor($user);

        // Garantiza que el iat del token sea anterior a la revocación.
        sleep(1);

        $this->postJson('/api/auth/reset-password', [
            'token' => $rawResetToken,
            'password' => 'ResetSecret123!',
            'password_confirmation' => 'ResetSecret123!',
        ])->assertOk();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/auth/profile')
            ->assertStatus(401);
    }
}
