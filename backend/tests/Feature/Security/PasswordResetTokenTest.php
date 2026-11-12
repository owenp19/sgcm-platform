<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordResetTokenTest extends SecurityTestCase
{
    public function test_el_token_de_reset_se_guarda_hasheado_y_permite_restablecer(): void
    {
        $rawToken = 'known-reset-token-123';
        $user = User::create([
            'first_name' => 'Reset',
            'last_name' => 'User',
            'email' => 'reset@test.local',
            'password' => Hash::make('OldPassword123!'),
            'reset_token' => hash('sha256', $rawToken),
            'reset_token_expires' => now()->addHour(),
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'ciudadano'])->id);

        $this->assertNotSame($rawToken, $user->fresh()->reset_token);

        $this->postJson('/api/auth/reset-password', [
            'token' => $rawToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $user->refresh();
        $this->assertNull($user->reset_token);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_el_hash_almacenado_no_sirve_como_token(): void
    {
        $user = User::create([
            'first_name' => 'Reset',
            'last_name' => 'Two',
            'email' => 'reset2@test.local',
            'password' => Hash::make('OldPassword123!'),
            'reset_token' => hash('sha256', 'another-token'),
            'reset_token_expires' => now()->addHour(),
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'ciudadano'])->id);

        $this->postJson('/api/auth/reset-password', [
            'token' => $user->reset_token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertStatus(400);
    }
}
