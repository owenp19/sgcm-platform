<?php

namespace Tests\Feature\Security;

use App\Models\Appointment;
use App\Models\ProcedureType;
use App\Models\RequestDocument;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthorizationSecurityTest extends SecurityTestCase
{
    private function makeUser(string $email, array $roles = ['ciudadano']): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('Secret123!'),
        ]);

        foreach ($roles as $role) {
            $user->roles()->attach(Role::firstOrCreate(['name' => $role])->id);
        }

        return $user;
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_funcionario_no_puede_autoasignarse_admin(): void
    {
        $funcionario = $this->makeUser('funcionario@test.local', ['funcionario']);
        $this->makeUser('ciudadano@test.local');
        Role::firstOrCreate(['name' => 'admin']);

        $response = $this->withHeaders($this->bearer($funcionario))
            ->putJson("/api/auth/users/{$funcionario->id}/roles", ['roles' => ['admin']]);

        $response->assertStatus(403);
        $this->assertFalse($funcionario->roles()->where('name', 'admin')->exists());
    }

    public function test_funcionario_no_puede_modificar_a_un_admin(): void
    {
        $funcionario = $this->makeUser('funcionario2@test.local', ['funcionario']);
        $admin = $this->makeUser('admin@test.local', ['admin']);
        Role::firstOrCreate(['name' => 'ciudadano']);

        $response = $this->withHeaders($this->bearer($funcionario))
            ->putJson("/api/auth/users/{$admin->id}/roles", ['roles' => ['ciudadano']]);

        $response->assertStatus(403);
        $this->assertTrue($admin->roles()->where('name', 'admin')->exists());
    }

    public function test_admin_si_puede_asignar_roles(): void
    {
        $admin = $this->makeUser('admin2@test.local', ['admin']);
        $target = $this->makeUser('target@test.local');
        Role::firstOrCreate(['name' => 'funcionario']);

        $response = $this->withHeaders($this->bearer($admin))
            ->putJson("/api/auth/users/{$target->id}/roles", ['roles' => ['funcionario']]);

        $response->assertOk();
        $this->assertTrue($target->roles()->where('name', 'funcionario')->exists());
    }

    public function test_ciudadano_no_puede_leer_la_cita_de_otro_usuario(): void
    {
        $owner = $this->makeUser('owner@test.local');
        $other = $this->makeUser('other@test.local');

        $appointment = Appointment::create([
            'user_id' => $owner->id,
            'appointment_code' => 'OC-TEST0001',
            'scheduled_date' => '2026-12-01',
            'scheduled_time' => '09:00:00',
            'citizen_full_name' => 'Owner Test',
            'citizen_document_number' => '123456789',
            'citizen_email' => 'owner@test.local',
            'status' => 'pendiente',
        ]);

        $this->withHeaders($this->bearer($other))
            ->getJson("/api/appointments/{$appointment->appointment_code}")
            ->assertStatus(403);
    }

    public function test_dueno_y_staff_pueden_leer_la_cita(): void
    {
        $owner = $this->makeUser('owner2@test.local');
        $staff = $this->makeUser('staff@test.local', ['funcionario']);

        $appointment = Appointment::create([
            'user_id' => $owner->id,
            'appointment_code' => 'OC-TEST0002',
            'scheduled_date' => '2026-12-02',
            'scheduled_time' => '10:00:00',
            'citizen_full_name' => 'Owner Two',
            'citizen_document_number' => '987654321',
            'citizen_email' => 'owner2@test.local',
            'status' => 'pendiente',
        ]);

        $this->withHeaders($this->bearer($owner))
            ->getJson("/api/appointments/{$appointment->appointment_code}")
            ->assertOk();

        $this->withHeaders($this->bearer($staff))
            ->getJson("/api/appointments/{$appointment->appointment_code}")
            ->assertOk();
    }

    public function test_funcionario_no_puede_cambiar_el_estado_de_su_propia_solicitud(): void
    {
        $staff = $this->makeUser('self-approve@test.local', ['funcionario']);
        $procedure = ProcedureType::create(['name' => 'Prueba', 'slug' => 'prueba-self']);

        $serviceRequest = ServiceRequest::create([
            'tracking_number' => 'SGCM-SELF-0001',
            'user_id' => $staff->id,
            'procedure_type_id' => $procedure->id,
            'status' => 'pendiente',
            'submitted_at' => now(),
        ]);

        $this->withHeaders($this->bearer($staff))
            ->patchJson("/api/requests/{$serviceRequest->id}/status", ['status' => 'aprobado'])
            ->assertStatus(403);

        $this->assertDatabaseHas('requests', [
            'id' => $serviceRequest->id,
            'status' => 'pendiente',
        ]);
    }

    public function test_ciudadano_no_puede_descargar_documento_de_otro_usuario(): void
    {
        $owner = $this->makeUser('doc-owner@test.local');
        $other = $this->makeUser('doc-other@test.local');
        $procedure = ProcedureType::create(['name' => 'Documentos', 'slug' => 'documentos-test']);

        $serviceRequest = ServiceRequest::create([
            'tracking_number' => 'SGCM-DOC-0001',
            'user_id' => $owner->id,
            'procedure_type_id' => $procedure->id,
            'status' => 'pendiente',
            'submitted_at' => now(),
        ]);

        $document = RequestDocument::create([
            'request_id' => $serviceRequest->id,
            'user_id' => $owner->id,
            'original_name' => 'cedula.pdf',
            'stored_name' => 'cedula.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_path' => 'documents/cedula.pdf',
            'document_type' => 'cedula',
            'created_at' => now(),
        ]);

        $this->withHeaders($this->bearer($other))
            ->getJson("/api/documents/{$document->id}/download")
            ->assertStatus(403);
    }

    public function test_ciudadano_no_puede_inyectar_campos_administrativos_al_crear_solicitud(): void
    {
        $citizen = $this->makeUser('mass-assignment@test.local');
        $other = $this->makeUser('mass-target@test.local');
        $procedure = ProcedureType::create(['name' => 'Mass', 'slug' => 'mass-test']);

        $response = $this->withHeaders($this->bearer($citizen))
            ->postJson('/api/requests', [
                'procedureTypeId' => $procedure->id,
                'notes' => 'Solicitud normal',
                'user_id' => $other->id,
                'status' => 'aprobado',
                'assigned_to' => $other->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('requests', [
            'id' => $response->json('requestId'),
            'user_id' => $citizen->id,
            'status' => 'pendiente',
        ]);
    }
}
