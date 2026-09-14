<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendUserNotificationJob;
use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Models\AppointmentService;
use App\Models\Office;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AppointmentController extends Controller
{
    private const MAX_SLOTS = 10;

    private const DAY_MAP = [
        0 => 'sunday', 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday',
        4 => 'thursday', 5 => 'friday', 6 => 'saturday',
    ];

    public function __construct(private readonly PushService $push)
    {
    }

    #[OA\Get(
        path: '/appointments/services',
        summary: 'Listar servicios disponibles para citas',
        tags: ['Appointments'],
        responses: [
            new OA\Response(response: 200, description: 'Lista de servicios'),
        ]
    )]
    public function getServices(): JsonResponse
    {
        $services = AppointmentService::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'description', 'icon', 'requires_documents as requiresDocuments', 'duration_minutes as durationMinutes']);

        return response()->json(['services' => $services]);
    }

    #[OA\Get(
        path: '/appointments/offices',
        summary: 'Listar oficinas activas',
        tags: ['Appointments'],
        responses: [
            new OA\Response(response: 200, description: 'Lista de oficinas'),
        ]
    )]
    public function getOffices(): JsonResponse
    {
        $offices = Office::where('is_active', true)->orderBy('id')->get(['id', 'name', 'address', 'phone']);

        return response()->json(['offices' => $offices]);
    }

    #[OA\Get(
        path: '/appointments/availability',
        summary: 'Consultar disponibilidad de horas para una cita',
        tags: ['Appointments'],
        parameters: [
            new OA\Parameter(name: 'office_id', in: 'query', required: true, description: 'ID de la oficina',
                schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'service_id', in: 'query', required: false, description: 'ID del servicio (opcional)',
                schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date', in: 'query', required: true, description: 'Fecha (formato Y-m-d)',
                schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Horas disponibles'),
            new OA\Response(response: 404, description: 'Oficina no encontrada'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function getAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'office_id' => ['required', 'integer'],
            'service_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $office = Office::where('id', $data['office_id'])->where('is_active', true)->first();
        if (! $office) {
            return response()->json(['error' => 'Oficina no encontrada'], 404);
        }

        $dateObj = \Carbon\CarbonImmutable::parse($data['date'].'T12:00:00Z');
        $dayName = self::DAY_MAP[$dateObj->dayOfWeek];
        $baseHours = $office->hours_config[$dayName] ?? [];

        if (empty($baseHours)) {
            return response()->json(['date' => $data['date'], 'available_hours' => []]);
        }

        $booked = Appointment::query()
            ->where('office_id', $data['office_id'])
            ->where('scheduled_date', $data['date'])
            ->whereNot('status', 'cancelada')
            ->whereIn('scheduled_time', $baseHours)
            ->groupBy('scheduled_time')
            ->selectRaw('scheduled_time, COUNT(*) as count')
            ->pluck('count', 'scheduled_time');

        $baseMinutes = isset($data['service_id'])
            ? (AppointmentService::where('id', $data['service_id'])->value('duration_minutes') ?? 15)
            : 15;

        $available = collect($baseHours)
            ->filter(fn ($h) => ($booked[$h] ?? 0) < self::MAX_SLOTS)
            ->map(fn ($h) => [
                'time' => $h,
                'label' => $this->formatTime12h($h),
                'available_count' => self::MAX_SLOTS - ($booked[$h] ?? 0),
            ])
            ->values();

        return response()->json([
            'date' => $data['date'],
            'duration_minutes' => $baseMinutes,
            'available_hours' => $available,
        ]);
    }

    private function formatTime12h(string $time24): string
    {
        [$h, $m] = array_map('intval', explode(':', $time24));
        $period = $h >= 12 ? 'p.m.' : 'a.m.';
        $hour12 = $h === 0 ? 12 : ($h > 12 ? $h - 12 : $h);

        return sprintf('%02d:%02d %s', $hour12, $m, $period);
    }

    private function generateCode(): string
    {
        return 'OC-'.Str::upper(Str::random(8));
    }

    #[OA\Post(
        path: '/appointments',
        summary: 'Agendar una nueva cita',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['scheduled_date', 'scheduled_time', 'citizen_full_name', 'citizen_email'],
                properties: [
                    new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'office_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'scheduled_date', type: 'string', format: 'date', example: '2026-09-20'),
                    new OA\Property(property: 'scheduled_time', type: 'string', example: '09:00'),
                    new OA\Property(property: 'citizen_full_name', type: 'string', example: 'Owen Pérez'),
                    new OA\Property(property: 'citizen_document_type', type: 'string', nullable: true, example: 'CC'),
                    new OA\Property(property: 'citizen_document_number', type: 'string', nullable: true, example: '1020304050'),
                    new OA\Property(property: 'citizen_email', type: 'string', format: 'email'),
                    new OA\Property(property: 'citizen_phone', type: 'string', nullable: true, example: '3001234567'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Observaciones adicionales'),
                    new OA\Property(property: 'documents', type: 'array', nullable: true,
                        items: new OA\Items(type: 'string', format: 'binary')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Cita agendada exitosamente'),
            new OA\Response(response: 400, description: 'Faltan campos obligatorios'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 409, description: 'La fecha y hora seleccionadas no están disponibles'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'office_id' => ['nullable', 'integer'],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'citizen_full_name' => ['required', 'string', 'max:200'],
            'citizen_document_type' => ['nullable', 'string', 'max:50'],
            'citizen_document_number' => ['nullable', 'string', 'max:50'],
            'citizen_email' => ['required', 'email', 'max:255'],
            'citizen_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png,doc,docx', 'max:10240'],
        ]);

        if (empty($data['citizen_full_name']) || empty($data['citizen_email'])) {
            return response()->json(['error' => 'Faltan campos obligatorios'], 400);
        }

        $bookedCount = Appointment::query()
            ->where('office_id', $data['office_id'] ?? null)
            ->where('scheduled_date', $data['scheduled_date'])
            ->where('scheduled_time', $data['scheduled_time'].':00')
            ->whereNot('status', 'cancelada')
            ->count();

        if ($bookedCount >= self::MAX_SLOTS) {
            return response()->json(['error' => 'La fecha y hora seleccionadas no están disponibles'], 409);
        }

        do {
            $appointmentCode = $this->generateCode();
        } while (Appointment::where('appointment_code', $appointmentCode)->exists());

        $files = $request->file('documents') ?? [];

        $appointmentId = DB::transaction(function () use ($user, $data, $appointmentCode, $files) {
            $appointment = Appointment::create([
                'user_id' => $user->id,
                'procedure_type_id' => $data['service_id'] ?? null,
                'office_id' => $data['office_id'] ?? null,
                'appointment_code' => $appointmentCode,
                'citizen_full_name' => $data['citizen_full_name'],
                'citizen_document_type' => $data['citizen_document_type'] ?? null,
                'citizen_document_number' => $data['citizen_document_number'] ?? null,
                'citizen_email' => $data['citizen_email'],
                'citizen_phone' => $data['citizen_phone'] ?? null,
                'scheduled_date' => $data['scheduled_date'],
                'scheduled_time' => $data['scheduled_time'].':00',
                'notes' => $data['notes'] ?? '',
                'status' => 'pendiente',
            ]);

            if (! empty($files)) {
                foreach ($files as $file) {
                    $storedName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
                    $path = 'uploads/appointments/'.$storedName;
                    Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

                    preg_match('/identity|photo|support|additional/i', $file->getClientOriginalName(), $matches);
                    $docType = $matches[0] !== null && $matches[0] !== ''
                        ? strtolower($matches[0])
                        : 'support';

                    AppointmentDocument::create([
                        'appointment_id' => $appointment->id,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $storedName,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'file_path' => $path,
                        'document_type' => $docType,
                        'created_at' => now(),
                    ]);
                }
            }

            $this->push->createNotification(
                $user->id,
                'Cita agendada',
                "Tu cita ha sido agendada para el {$data['scheduled_date']} a las {$data['scheduled_time']}. Código: {$appointmentCode}",
                'success'
            );

            return $appointment->id;
        });

        SendUserNotificationJob::dispatch(
            $user->id,
            'Cita agendada',
            "Tu cita ha sido agendada para el {$data['scheduled_date']} a las {$data['scheduled_time']}. Código: {$appointmentCode}",
            'success',
            ['type' => 'appointment', 'appointmentId' => (string) $appointmentId, 'code' => $appointmentCode]
        );

        return response()->json([
            'success' => true,
            'message' => 'Cita agendada exitosamente',
            'data' => [
                'id' => $appointmentId,
                'code' => $appointmentCode,
                'scheduled_date' => $data['scheduled_date'],
                'scheduled_time' => $data['scheduled_time'],
            ],
        ], 201);
    }

    #[OA\Get(
        path: '/appointments/{code}',
        summary: 'Obtener una cita por su código',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, description: 'Código de la cita',
                schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de la cita'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 404, description: 'Cita no encontrada'),
        ]
    )]
    public function getAppointmentByCode(string $code): JsonResponse
    {
        $a = Appointment::query()
            ->leftJoin('offices as o', 'appointments.office_id', '=', 'o.id')
            ->leftJoin('appointment_services as s', 'appointments.procedure_type_id', '=', 's.id')
            ->where('appointments.appointment_code', $code)
            ->select('appointments.*', 'o.name as office_name', 'o.address as office_address',
                's.name as service_name', 's.slug as service_slug', 's.icon as service_icon')
            ->first();

        if (! $a) {
            return response()->json(['error' => 'Cita no encontrada'], 404);
        }

        $documents = AppointmentDocument::where('appointment_id', $a->id)
            ->get(['id', 'original_name', 'mime_type', 'file_size', 'document_type', 'created_at']);

        return response()->json([
            'appointment' => [
                'id' => $a->id,
                'code' => $a->appointment_code,
                'serviceName' => $a->service_name,
                'serviceSlug' => $a->service_slug,
                'serviceIcon' => $a->service_icon,
                'officeName' => $a->office_name,
                'officeAddress' => $a->office_address,
                'citizenFullName' => $a->citizen_full_name,
                'citizenDocumentType' => $a->citizen_document_type,
                'citizenDocumentNumber' => $a->citizen_document_number,
                'citizenEmail' => $a->citizen_email,
                'citizenPhone' => $a->citizen_phone,
                'scheduledDate' => $a->scheduled_date,
                'scheduledTime' => substr((string) $a->scheduled_time, 0, 5),
                'status' => $a->status,
                'notes' => $a->notes,
                'documents' => $documents,
                'createdAt' => $a->created_at?->toISOString(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/appointments/my',
        summary: 'Listar citas del usuario autenticado',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de citas del usuario'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function getMyAppointments(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $rows = Appointment::query()
            ->leftJoin('offices as o', 'appointments.office_id', '=', 'o.id')
            ->leftJoin('appointment_services as s', 'appointments.procedure_type_id', '=', 's.id')
            ->where('appointments.user_id', $user->id)
            ->orderByDesc('appointments.scheduled_date')
            ->orderByDesc('appointments.scheduled_time')
            ->select('appointments.*', 'o.name as office_name', 's.name as service_name', 's.icon as service_icon')
            ->get();

        return response()->json([
            'appointments' => $rows->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->appointment_code,
                'serviceName' => $a->service_name,
                'serviceIcon' => $a->service_icon,
                'officeName' => $a->office_name,
                'citizenFullName' => $a->citizen_full_name,
                'citizenDocumentType' => $a->citizen_document_type,
                'citizenDocumentNumber' => $a->citizen_document_number,
                'citizenEmail' => $a->citizen_email,
                'citizenPhone' => $a->citizen_phone,
                'scheduledDate' => $a->scheduled_date,
                'scheduledTime' => substr((string) $a->scheduled_time, 0, 5),
                'status' => $a->status,
                'notes' => $a->notes,
                'createdAt' => $a->created_at?->toISOString(),
            ]),
        ]);
    }

    #[OA\Patch(
        path: '/appointments/{id}/cancel',
        summary: 'Cancelar una cita',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID de la cita',
                schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cita cancelada exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'No tienes permisos para cancelar esta cita'),
            new OA\Response(response: 404, description: 'Cita no encontrada'),
        ]
    )]
    public function cancel(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $appointment = Appointment::find($id);
        if (! $appointment) {
            return response()->json(['error' => 'Cita no encontrada'], 404);
        }

        if ($appointment->user_id !== $user->id && ! $user->hasRole('admin')) {
            return response()->json(['error' => 'No tienes permisos para cancelar esta cita'], 403);
        }

        $appointment->update(['status' => 'cancelada']);

        return response()->json(['message' => 'Cita cancelada exitosamente']);
    }

    #[OA\Get(
        path: '/appointments/all',
        summary: 'Listar todas las citas (admin/funcionario)',
        tags: ['Appointments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Filtrar por fecha (formato Y-m-d)',
                schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Filtrar por estado',
                schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de citas'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos para consultar'),
        ]
    )]
    public function getAllAppointments(Request $request): JsonResponse
    {
        $query = Appointment::query()
            ->leftJoin('offices as o', 'appointments.office_id', '=', 'o.id')
            ->leftJoin('appointment_services as s', 'appointments.procedure_type_id', '=', 's.id')
            ->join('users as u', 'appointments.user_id', '=', 'u.id');

        if ($date = $request->query('date')) {
            $query->where('appointments.scheduled_date', $date);
        }
        if ($status = $request->query('status')) {
            $query->where('appointments.status', $status);
        }

        $rows = $query->orderBy('appointments.scheduled_date')
            ->orderBy('appointments.scheduled_time')
            ->select('appointments.*', 'o.name as office_name', 's.name as service_name', 's.icon as service_icon',
                \Illuminate\Support\Facades\DB::raw("CONCAT(u.first_name, ' ', u.last_name) as citizen_name"),
                'u.email as citizen_email', 'u.phone as citizen_phone')
            ->get();

        return response()->json([
            'appointments' => $rows->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->appointment_code,
                'citizenName' => $a->citizen_name,
                'citizenEmail' => $a->citizen_email,
                'citizenPhone' => $a->citizen_phone,
                'serviceName' => $a->service_name,
                'serviceIcon' => $a->service_icon,
                'officeName' => $a->office_name,
                'scheduledDate' => $a->scheduled_date,
                'scheduledTime' => substr((string) $a->scheduled_time, 0, 5),
                'status' => $a->status,
                'notes' => $a->notes,
                'createdAt' => $a->created_at?->toISOString(),
            ]),
        ]);
    }
}
