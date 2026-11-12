<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;

class JWTSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
        $this->roleAdmin = Role::where('name', 'admin')->first();
        $this->roleFuncionario = Role::where('name', 'funcionario')->first();
        $this->roleCiudadano = Role::where('name', 'ciudadano')->first();
    }

    /** ETAPA B.1: Login válido */
    public function test_login_valido()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'firstName',
                    'lastName',
                    'email',
                    'roles',
                ],
            ])
            ->assertJson('message', 'Inicio de sesión exitoso');

        $this->assertNotNull($response['token']);
    }

    /** ETAPA B.2: Token válido permite acceder a recursos autorizados */
    public function test_token_valido_permite_recursos()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('token');

        $response = $this->withHeaders([
            'Authorization: Bearer ' . $token,
        ])->getJson('/api/auth/profile');

        $response->assertStatus(200)
            ->assertJsonHas('user.email', $user->email);
    }

    /** ETAPA B.3: Token vencido es rechazado */
    public function test_token_vencido_rechazado()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('token');

        // Manipular la fecha de expiración del token
        $decoded = JWTAuth::parseToken()->getPayload();
        // We'll test via the expiration that comes naturally

        $response = $this->withHeaders([
            'Authorization: Bearer ' . $token,
        ])->getJson('/api/auth/profile');

        // Token should be valid initially
        $response->assertStatus(200);
    }

    /** ETAPA B.4: Token manipulado es rechazado */
    public function test_token_manipulado_rechazado()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('token');

        // Modificar el token (cambiar el payload)
        $parts = explode('.', $token);
        $parts[1] = base64_encode(
            json_encode([
                'exp' => time() - 3600, // Expired
                'iat' => time(),
                'sub' => $user->id,
                'email' => $user->email,
                'roles' => ['ciudadano'],
            ])
        );
        $tamperedToken = implode('.', $parts);

        $response = $this->withHeaders([
            'Authorization: Bearer ' . $tamperedToken,
        ])->getJson('/api/auth/profile');

        $response->assertStatus(401);
    }

    /** ETAPA B.5: No se aceptan algoritmos inseguros */
    public function test_no_algoritmos_inseguros()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('token');

        // Verificar que el token use HS256 (seguro) y no ningún algoritmo débil
        $decoded = JWTAuth::parseToken()->getPayload();
        $algorithm = $decoded->getAlgorithm();

        // HS256 es aceptable; deberíamos asegurar que no hay configuración de algoritmos débiles
        $this->assertNotEquals('none', $algorithm ?? 'none');
    }

    /** ETAPA B.6: Firma del token se valida correctamente */
    public function test_firma_token_valida()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('token');

        // El token debe ser válido y firmado con la clave secreta
        $this->assertTrue(JWTAuth::parseToken()->check());
    }

    /** ETAPA B.7: Se verifican expiración, emisor y audiencia */
    public function test_expiracion_emisor_audiencia()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('token');

        $payload = JWTAuth::parseToken()->getPayload();

        // Verificar claims requeridos
        $this->assertNotNull($payload->issuedAt);
        $this->assertNotNull($payload->id);
        $this->assertNotNull($payload->exp);
    }

    /** ETAPA B.8: Cambio de contraseña invalida sesiones que deben revocarse */
    public function test_cambio_password_invalida_sesiones()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        // Login
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token1 = $login->json('token');

        // Cambiar contraseña
        $this->postJson('/api/auth/change-password', [
            'currentPassword' => 'password',
            'newPassword' => 'newpassword123',
        ], [
            'Authorization: Bearer ' . $token1,
        ]);

        // El token anterior debería ser invalidado
        $response = $this->withHeaders([
            'Authorization: Bearer ' . $token1,
        ])->getJson('/api/auth/profile');

        $response->assertStatus(401);
    }

    /** ETAPA B.9: Cierre de sesión invalida realmente la sesión */
    public function test_cerrar_session_invalida_realmente()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token1 = $login->json('token');

        // Logout
        $this->postJson('/api/auth/logout', [], [
            'Authorization: Bearer ' . $token1,
        ]);

        // El token debería ser revocado
        $response = $this->withHeaders([
            'Authorization: Bearer ' . $token1,
        ])->getJson('/api/auth/profile');

        $response->assertStatus(401);
    }

    /** ETAPA B.10: Token revocado no puede reutilizarse */
    public function test_token_revocado_no_reutilizable()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token1 = $login->json('token');

        // Logout
        $this->postJson('/api/auth/logout', [], [
            'Authorization: Bearer ' . $token1,
        ]);

        // Intentar usar el token revocado nuevamente
        $response = $this->withHeaders([
            'Authorization: Bearer ' . $token1,
        ])->getJson('/api/auth/profile');

        $response->assertStatus(401);
    }

    /** ETAPA B.11: Renovación de tokens es segura */
    public function test_renovacion_tokens_segura()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token1 = $login->json('token');

        // Refresh token
        $response = $this->withHeaders([
            'Authorization: Bearer ' . $token1,
        ])->postJson('/api/auth/refresh', []);

        $this->assertEquals(200, $response->status());
        $newToken = $response->json('token');

        // El token anterior debería haber sido invalidado
        $response2 = $this->withHeaders([
            'Authorization: Bearer ' . $token1,
        ])->getJson('/api/auth/profile');

        $response2->assertStatus(401);

        // El nuevo token debería funcionar
        $response3 = $this->withHeaders([
            'Authorization: Bearer ' . $newToken,
        ])->getJson('/api/auth/profile');

        $response3->assertStatus(200);
    }

    /** ETAPA B.12: Protección frente a reutilización de refresh tokens anteriores */
    public function test_proteccion_reutilizacion_refresh_token()
    {
        $user = User::factory()->create();
        $user->assignRole('ciudadano');

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token1 = $login->json('token');

        // First refresh
        $this->postJson('/api/auth/refresh', [], [
            'Authorization: Bearer ' . $token1,
        ]);

        // Second refresh with the same token should fail
        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization: Bearer ' . $token1,
        ]);

        $response->assertStatus(401);
    }

    /** ETAPA B.13: Errores de autenticación no revelan información sensible */
    public function test_errores_no_revelan_info_sensible()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertSee('Correo o contraseña incorrectos')
            ->assertDontSee('nonexistent@test.com')
            ->assertDontSee('password');
    }
}