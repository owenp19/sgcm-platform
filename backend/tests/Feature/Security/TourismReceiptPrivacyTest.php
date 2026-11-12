<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\TouristCard;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class TourismReceiptPrivacyTest extends SecurityTestCase
{
    private function makeCard(string $code): TouristCard
    {
        return TouristCard::create([
            'code' => $code,
            'first_name' => 'Turista',
            'last_name' => 'Privado',
            'document_type' => 'PAS',
            'document_number' => 'AB1234567',
            'email' => 'turista@example.com',
            'phone' => '3001234567',
            'country_residence' => 'Panamá',
            'city_residence' => 'Colón',
            'emergency_contact_name' => 'Contacto Emergencia',
            'emergency_contact_phone' => '3107654321',
            'entry_date' => '2026-11-01',
            'return_date' => '2026-11-15',
            'lodging_name' => 'Hotel Secreto',
            'lodging_address' => 'Calle Privada 123',
            'lodging_phone' => '3201112233',
            'lodging_responsible_name' => 'Responsable Hotel',
            'amount' => 50000,
            'currency' => 'COP',
            'payment_status' => 'Pagado',
            'card_status' => 'Emitida',
        ]);
    }

    private function staffBearer(): array
    {
        $user = User::create([
            'first_name' => 'Staff',
            'last_name' => 'Seguridad',
            'email' => 'staff-receipt@test.local',
            'password' => Hash::make('Secret123!'),
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'funcionario'])->id);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    private function signedTokenFor(TouristCard $card): string
    {
        $payload = $card->id.'.'.$card->code.'.'.now()->addDay()->timestamp;
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));

        return rtrim(strtr(base64_encode($payload.'.'.$signature), '+/', '-_'), '=');
    }

    public function test_codigo_antiguo_de_seis_digitos_no_expone_pii(): void
    {
        $this->makeCard('123456');

        $response = $this->getJson('/api/tourism-card/card/123456/receipt')
            ->assertOk()
            ->assertJsonPath('pii_masked', true);

        $response->assertJsonPath('first_name', 'T******')
            ->assertJsonPath('email', 't***@example.com')
            ->assertJsonPath('birth_date', null)
            ->assertJsonPath('pdf_path', null)
            ->assertJsonPath('qr_payload', null);

        $this->assertStringNotContainsString('AB1234567', $response->getContent());
        $this->assertStringNotContainsString('3001234567', $response->getContent());
        $this->assertStringNotContainsString('Calle Privada 123', $response->getContent());
    }

    public function test_codigo_de_alta_entropia_puede_consultar_el_recibo_completo(): void
    {
        $code = 'SGCM-TT-2026-ABCDEFGHJKLM';
        $this->makeCard($code);

        $this->getJson("/api/tourism-card/card/{$code}/receipt")
            ->assertOk()
            ->assertJsonPath('pii_masked', false)
            ->assertJsonPath('email', 'turista@example.com')
            ->assertJsonPath('document_number', 'AB1234567');
    }

    public function test_staff_autenticado_puede_ver_recibo_antiguo_completo(): void
    {
        $this->makeCard('654321');

        $this->withHeaders($this->staffBearer())
            ->getJson('/api/tourism-card/card/654321/receipt')
            ->assertOk()
            ->assertJsonPath('pii_masked', false)
            ->assertJsonPath('email', 'turista@example.com');
    }

    public function test_token_firmado_permite_acceso_completo_a_codigo_antiguo(): void
    {
        $card = $this->makeCard('111222');
        $token = $this->signedTokenFor($card);

        $this->getJson("/api/tourism-card/card/111222/receipt?token={$token}")
            ->assertOk()
            ->assertJsonPath('pii_masked', false)
            ->assertJsonPath('email', 'turista@example.com');
    }

    public function test_token_firmado_manipulado_no_permite_acceso_completo(): void
    {
        $this->makeCard('333444');

        $this->getJson('/api/tourism-card/card/333444/receipt?token=token-invalido')
            ->assertOk()
            ->assertJsonPath('pii_masked', true);
    }

    public function test_compartir_ubicacion_rechaza_coordenadas_invalidas_o_ausentes(): void
    {
        $this->makeCard('555666');

        $this->postJson('/api/tourism-card/card/555666/share-location', [
            'latitude' => null,
            'longitude' => null,
        ])->assertStatus(400);

        $this->postJson('/api/tourism-card/card/555666/share-location', [
            'latitude' => 120,
            'longitude' => 200,
        ])->assertStatus(400);
    }

    public function test_crear_tarjeta_entrega_credencial_de_recibo(): void
    {
        $response = $this->postJson('/api/tourism-card/card', [
            'first_name' => 'Nuevo',
            'last_name' => 'Turista',
            'document_type' => 'PAS',
            'document_number' => 'ZX9988776',
            'email' => 'nuevo@example.com',
            'phone' => '3009998877',
            'entry_date' => '2026-12-01',
            'return_date' => '2026-12-10',
            'accepted_terms' => true,
        ])->assertCreated();

        $this->assertNotEmpty($response->json('data.receipt_token'));
        $this->assertMatchesRegularExpression(
            '/^SGCM-TT-\d{4}-[A-Z0-9]{12}$/',
            $response->json('data.code')
        );
    }
}
