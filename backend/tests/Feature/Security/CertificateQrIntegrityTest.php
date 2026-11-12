<?php

namespace Tests\Feature\Security;

use App\Models\ProcedureType;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CertificateQrIntegrityTest extends SecurityTestCase
{
    private function makeRequest(string $status): ServiceRequest
    {
        $user = User::create([
            'first_name' => 'Ana',
            'last_name' => 'Perez',
            'email' => 'ana'.uniqid().'@test.local',
            'password' => Hash::make('Secret123!'),
            'document_type' => 'CC',
            'document_number' => '1020304050',
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'ciudadano'])->id);

        $procedure = ProcedureType::create([
            'name' => 'Certificado de Residencia',
            'slug' => 'certificado-'.uniqid(),
            'is_active' => true,
        ]);

        return ServiceRequest::create([
            'tracking_number' => 'SGCM'.uniqid(),
            'user_id' => $user->id,
            'procedure_type_id' => $procedure->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }

    private function payloadFor(ServiceRequest $request): array
    {
        $payload = [
            'type' => 'occre_certificate',
            'tracking' => $request->tracking_number,
            'status' => $request->status,
            'citizen' => 'Ana Perez',
            'doc' => 'CC 1020304050',
            'procedure' => $request->procedureType->name,
        ];

        $payload['signature'] = hash_hmac('sha256', json_encode($payload), (string) config('app.key'));

        return $payload;
    }

    public function test_qr_firmado_de_solicitud_aprobada_es_valido(): void
    {
        $request = $this->makeRequest('aprobado');

        $this->postJson('/api/certificates/validate-qr', ['qrData' => json_encode($this->payloadFor($request))])
            ->assertOk()
            ->assertJson(['valid' => true]);
    }

    public function test_qr_de_solicitud_no_aprobada_es_rechazado(): void
    {
        $request = $this->makeRequest('pendiente');

        $this->postJson('/api/certificates/validate-qr', ['qrData' => json_encode($this->payloadFor($request))])
            ->assertNotFound()
            ->assertJson(['valid' => false]);
    }

    public function test_qr_manipulado_es_rechazado(): void
    {
        $request = $this->makeRequest('finalizado');
        $payload = $this->payloadFor($request);
        $payload['citizen'] = 'Atacante Falso';

        $this->postJson('/api/certificates/validate-qr', ['qrData' => json_encode($payload)])
            ->assertStatus(400)
            ->assertJson(['valid' => false]);
    }

    public function test_qr_antiguo_sin_firma_solo_pasa_si_los_datos_coinciden(): void
    {
        $request = $this->makeRequest('finalizado');
        $payload = $this->payloadFor($request);
        unset($payload['signature']);

        $this->postJson('/api/certificates/validate-qr', ['qrData' => json_encode($payload)])
            ->assertOk()
            ->assertJson(['valid' => true]);

        $payload['doc'] = 'CC 9999999999';
        $this->postJson('/api/certificates/validate-qr', ['qrData' => json_encode($payload)])
            ->assertStatus(400)
            ->assertJson(['valid' => false]);
    }
}
