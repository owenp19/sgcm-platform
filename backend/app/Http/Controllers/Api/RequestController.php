<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendUserNotificationJob;
use App\Models\AuditLog;
use App\Models\RequestHistory;
use App\Models\ServiceRequest;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class RequestController extends Controller
{
    private const STATUS_LABELS = [
        'borrador' => 'Borrador',
        'pendiente' => 'Radicado',
        'en_revision' => 'En revisión',
        'devuelto' => 'Observado',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
        'finalizado' => 'Finalizado',
    ];

    public function __construct(private readonly PushService $push)
    {
    }

    private function generateTrackingNumber(): string
    {
        return 'SGCM'.now()->format('Ymd').Str::upper(Str::substr(Str::uuid(), 0, 6));
    }

    #[OA\Post(
        path: '/requests',
        summary: 'Crear (radicar) una nueva solicitud',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['procedureTypeId'],
                properties: [
                    new OA\Property(property: 'procedureTypeId', type: 'integer'),
                    new OA\Property(property: 'notes', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Solicitud radicada exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'procedureTypeId' => ['required', 'integer', 'exists:procedure_types,id'],
            'notes' => ['nullable', 'string'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $trackingNumber = $this->generateTrackingNumber();

        $requestId = DB::transaction(function () use ($user, $data, $trackingNumber) {
            $req = ServiceRequest::create([
                'tracking_number' => $trackingNumber,
                'user_id' => $user->id,
                'procedure_type_id' => $data['procedureTypeId'],
                'status' => 'pendiente',
                'notes' => $data['notes'] ?? '',
                'submitted_at' => now(),
            ]);

            RequestHistory::create([
                'request_id' => $req->id,
                'user_id' => $user->id,
                'to_status' => 'pendiente',
                'action' => 'created',
                'comment' => 'Solicitud creada',
                'created_at' => now(),
            ]);

            AuditLog::record($user->id, 'create_request', 'request', $req->id, [
                'trackingNumber' => $trackingNumber,
                'procedureTypeId' => $data['procedureTypeId'],
            ]);

            return $req->id;
        });

        SendUserNotificationJob::dispatch(
            $user->id,
            'Solicitud radicada',
            "Tu solicitud ha sido radicada con el número {$trackingNumber}",
            'success',
            ['type' => 'request', 'requestId' => (string) $requestId, 'trackingNumber' => $trackingNumber]
        );

        return response()->json([
            'message' => 'Solicitud radicada exitosamente',
            'requestId' => $requestId,
            'trackingNumber' => $trackingNumber,
        ], 201);
    }

    #[OA\Get(
        path: '/requests/my',
        summary: 'Obtener las solicitudes del usuario autenticado',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de solicitudes del usuario'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function getMyRequests(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $page = max(1, (int) $request->query('page', '1'));
        $limit = max(1, (int) $request->query('limit', '20'));

        $query = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->where('requests.user_id', $user->id)
            ->orderByDesc('requests.submitted_at');

        $total = (clone $query)->count();
        $rows = $query->select([
            'requests.id', 'requests.tracking_number', 'requests.status', 'requests.priority',
            'requests.notes', 'requests.submitted_at', 'requests.resolved_at',
            'pt.name as procedure_name', 'pt.slug as procedure_slug',
        ])->forPage($page, $limit)->get();

        return response()->json([
            'requests' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'trackingNumber' => $r->tracking_number,
                'status' => $r->status,
                'priority' => $r->priority,
                'procedureName' => $r->procedure_name,
                'procedureSlug' => $r->procedure_slug,
                'notes' => $r->notes,
                'submittedAt' => $r->submitted_at?->toISOString(),
                'resolvedAt' => $r->resolved_at?->toISOString(),
            ]),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[OA\Get(
        path: '/requests/tracking/{trackingNumber}',
        summary: 'Consultar una solicitud por número de radicado',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'trackingNumber', in: 'path', required: true,
                schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de la solicitud'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin acceso a la solicitud'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
        ]
    )]
    public function getByTrackingNumber(Request $request, string $trackingNumber): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $isStaff = $user->hasRole('admin', 'funcionario');

        $req = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->leftJoin('users as assigned', 'requests.assigned_to', '=', 'assigned.id')
            ->where('requests.tracking_number', $trackingNumber)
            ->select('requests.*', 'pt.name as procedure_name', 'pt.slug as procedure_slug',
                'pt.description as procedure_description',
                DB::raw("CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_name"))
            ->first();

        if (! $req) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        if ($req->user_id !== $user->id && ! $isStaff) {
            return response()->json(['error' => 'No tienes acceso a esta solicitud'], 403);
        }

        $history = RequestHistory::query()
            ->leftJoin('users as u', 'request_history.user_id', '=', 'u.id')
            ->where('request_history.request_id', $req->id)
            ->orderBy('request_history.created_at')
            ->get(['request_history.*', DB::raw("CONCAT(u.first_name, ' ', u.last_name) as user_name")]);

        $documents = \App\Models\RequestDocument::where('request_id', $req->id)->get();

        return response()->json([
            'request' => [
                'id' => $req->id,
                'trackingNumber' => $req->tracking_number,
                'status' => $req->status,
                'priority' => $req->priority,
                'procedureName' => $req->procedure_name,
                'procedureSlug' => $req->procedure_slug,
                'procedureDescription' => $req->procedure_description,
                'notes' => $req->notes,
                'internalNotes' => $isStaff ? $req->internal_notes : null,
                'assignedTo' => $req->assigned_name,
                'submittedAt' => $req->submitted_at?->toISOString(),
                'resolvedAt' => $req->resolved_at?->toISOString(),
                'updatedAt' => $req->updated_at?->toISOString(),
                'history' => $history->map(fn ($h) => [
                    'id' => $h->id,
                    'fromStatus' => $h->from_status,
                    'toStatus' => $h->to_status,
                    'action' => $h->action,
                    'comment' => $h->comment,
                    'userName' => $h->user_name,
                    'createdAt' => $h->created_at?->toISOString(),
                ]),
                'documents' => $documents->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'mimeType' => $d->mime_type,
                    'size' => $d->file_size,
                    'documentType' => $d->document_type,
                    'isValidated' => (bool) $d->is_validated,
                    'createdAt' => $d->created_at?->toISOString(),
                ]),
            ],
        ]);
    }

    #[OA\Patch(
        path: '/requests/{id}/status',
        summary: 'Actualizar el estado de una solicitud',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'en_revision'),
                    new OA\Property(property: 'comment', type: 'string'),
                    new OA\Property(property: 'internalNotes', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Estado actualizado exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos para actualizar el estado'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:borrador,pendiente,en_revision,devuelto,aprobado,rechazado,finalizado'],
            'comment' => ['nullable', 'string'],
            'internalNotes' => ['nullable', 'string'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $isStaff = $user->hasRole('admin', 'funcionario');

        $req = ServiceRequest::find($id);
        if (! $req) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        $fromStatus = $req->status;
        $status = $data['status'];

        DB::transaction(function () use ($req, $fromStatus, $status, $data, $user, $isStaff) {
            $req->update(['status' => $status, 'updated_at' => now()]);

            if (! empty($data['internalNotes']) && $isStaff) {
                $entry = "\n[".now()->toISOString()."] {$user->first_name} {$user->last_name}: {$data['internalNotes']}";
                $req->forceFill(['internal_notes' => ($req->internal_notes ?? '').$entry])->save();
            }

            RequestHistory::create([
                'request_id' => $req->id,
                'user_id' => $user->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'action' => "status_change_to_{$status}",
                'comment' => $data['comment'] ?? '',
                'created_at' => now(),
            ]);

            if (in_array($status, ['rechazado', 'finalizado'])) {
                $req->forceFill(['resolved_at' => now()])->save();
            }

            AuditLog::record($user->id, 'update_request_status', 'request', $req->id, [
                'from' => $fromStatus,
                'to' => $status,
                'comment' => $data['comment'] ?? null,
            ]);
        });

        $statusMessages = [
            'en_revision' => 'Tu solicitud está en revisión',
            'devuelto' => 'Tu solicitud ha sido devuelta para corrección',
            'aprobado' => 'Tu solicitud ha sido aprobada',
            'rechazado' => 'Tu solicitud ha sido rechazada',
            'finalizado' => 'Tu solicitud ha sido finalizada',
        ];

        if (isset($statusMessages[$status])) {
            $type = in_array($status, ['aprobado', 'finalizado']) ? 'success'
                : (in_array($status, ['rechazado', 'devuelto']) ? 'alert' : 'info');
            $description = "{$statusMessages[$status]} ({$req->tracking_number})";

            SendUserNotificationJob::dispatch(
                $req->user_id,
                'Estado de solicitud actualizado',
                $description,
                $type,
                [
                    'type' => 'request',
                    'requestId' => (string) $req->id,
                    'trackingNumber' => $req->tracking_number,
                ]
            );
        }

        return response()->json([
            'message' => 'Estado actualizado exitosamente',
            'fromStatus' => $fromStatus,
            'toStatus' => $status,
        ]);
    }

    #[OA\Patch(
        path: '/requests/{id}/assign',
        summary: 'Asignar una solicitud a un funcionario',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'assignedTo', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Solicitud asignada exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos para asignar'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function assignRequest(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $req = ServiceRequest::find($id);
        if (! $req) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        $assignedTo = $data['assignedTo'] ?? null;

        DB::transaction(function () use ($req, $assignedTo, $user) {
            $req->update(['assigned_to' => $assignedTo, 'updated_at' => now()]);

            RequestHistory::create([
                'request_id' => $req->id,
                'user_id' => $user->id,
                'action' => 'assigned',
                'comment' => $assignedTo ? "Asignada a usuario {$assignedTo}" : 'Desasignada',
                'created_at' => now(),
            ]);

            AuditLog::record($user->id, 'assign_request', 'request', $req->id, ['assignedTo' => $assignedTo]);
        });

        if ($assignedTo && \App\Models\User::find($assignedTo)) {
            SendUserNotificationJob::dispatch(
                (int) $assignedTo,
                'Solicitud asignada',
                'Se te ha asignado una nueva solicitud para revisión',
                'info',
                ['type' => 'request', 'requestId' => (string) $req->id]
            );
        }

        return response()->json(['message' => 'Solicitud asignada exitosamente']);
    }

    #[OA\Get(
        path: '/requests/all',
        summary: 'Obtener todas las solicitudes con filtros (staff)',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'assignedTo', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'procedureTypeId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'dateFrom', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateTo', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de solicitudes'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos para consultar todas las solicitudes'),
        ]
    )]
    public function getAllRequests(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $limit = max(1, (int) $request->query('limit', '20'));

        $query = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->join('users as u', 'requests.user_id', '=', 'u.id')
            ->leftJoin('users as assigned', 'requests.assigned_to', '=', 'assigned.id');

        if ($status = $request->query('status')) {
            $query->whereIn('requests.status', explode(',', $status));
        }
        if ($priority = $request->query('priority')) {
            $query->where('requests.priority', $priority);
        }
        if ($assignedTo = $request->query('assignedTo')) {
            $query->where('requests.assigned_to', $assignedTo);
        }
        if ($procedureTypeId = $request->query('procedureTypeId')) {
            $query->where('requests.procedure_type_id', $procedureTypeId);
        }
        if ($search = $request->query('search')) {
            $term = "%{$search}%";
            $query->where(function ($q) use ($term) {
                $q->where('requests.tracking_number', 'like', $term)
                    ->orWhereRaw("CONCAT(u.first_name, ' ', u.last_name) LIKE ?", [$term])
                    ->orWhere('u.email', 'like', $term);
            });
        }
        if ($dateFrom = $request->query('dateFrom')) {
            $query->where('requests.submitted_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('dateTo')) {
            $query->where('requests.submitted_at', '<=', $dateTo);
        }

        $total = (clone $query)->count();

        $rows = $query->select([
            'requests.id', 'requests.tracking_number', 'requests.status', 'requests.priority',
            'requests.submitted_at', 'requests.resolved_at',
            'pt.name as procedure_name',
            DB::raw("CONCAT(u.first_name, ' ', u.last_name) as citizen_name"),
            'u.email as citizen_email',
            DB::raw("CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_name"),
        ])
            ->orderByRaw("CASE requests.priority WHEN 'urgente' THEN 0 WHEN 'alta' THEN 1 WHEN 'normal' THEN 2 WHEN 'baja' THEN 3 ELSE 4 END")
            ->orderBy('requests.submitted_at')
            ->forPage($page, $limit)
            ->get();

        $statusCounts = ServiceRequest::query()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as count')
            ->pluck('count', 'status');

        return response()->json([
            'requests' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'trackingNumber' => $r->tracking_number,
                'status' => $r->status,
                'priority' => $r->priority,
                'procedureName' => $r->procedure_name,
                'citizenName' => $r->citizen_name,
                'citizenEmail' => $r->citizen_email,
                'assignedName' => $r->assigned_name,
                'submittedAt' => $r->submitted_at?->toISOString(),
                'resolvedAt' => $r->resolved_at?->toISOString(),
            ]),
            'statusCounts' => $statusCounts,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[OA\Get(
        path: '/requests/assigned',
        summary: 'Obtener las solicitudes asignadas al usuario autenticado',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de solicitudes asignadas'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function getMyAssignedRequests(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $page = max(1, (int) $request->query('page', '1'));
        $limit = max(1, (int) $request->query('limit', '20'));

        $query = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->join('users as u', 'requests.user_id', '=', 'u.id')
            ->where('requests.assigned_to', $user->id);

        if ($status = $request->query('status')) {
            $query->where('requests.status', $status);
        }

        $total = (clone $query)->count();

        $rows = $query->select([
            'requests.id', 'requests.tracking_number', 'requests.status', 'requests.priority',
            'requests.submitted_at', 'pt.name as procedure_name',
            DB::raw("CONCAT(u.first_name, ' ', u.last_name) as citizen_name"),
        ])
            ->orderByRaw("CASE requests.priority WHEN 'urgente' THEN 0 WHEN 'alta' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('requests.submitted_at')
            ->forPage($page, $limit)
            ->get();

        return response()->json([
            'requests' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'trackingNumber' => $r->tracking_number,
                'status' => $r->status,
                'priority' => $r->priority,
                'procedureName' => $r->procedure_name,
                'citizenName' => $r->citizen_name,
                'submittedAt' => $r->submitted_at?->toISOString(),
            ]),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[OA\Post(
        path: '/requests/search-record',
        summary: 'Buscar un trámite por número de radicado y documento',
        tags: ['Requests'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['record_number', 'document_number'],
                properties: [
                    new OA\Property(property: 'record_number', type: 'string', example: 'SGCM20260901ABC123'),
                    new OA\Property(property: 'document_number', type: 'string', example: '1020304050'),
                    new OA\Property(property: 'document_type', type: 'string', example: 'CC'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Trámite encontrado'),
            new OA\Response(response: 404, description: 'Trámite no encontrado con esos datos'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function searchRecord(Request $request): JsonResponse
    {
        $data = $request->validate([
            'record_number' => ['required', 'string'],
            'document_number' => ['required', 'string'],
            'document_type' => ['nullable', 'string'],
        ]);

        $req = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->join('users as u', 'requests.user_id', '=', 'u.id')
            ->where('requests.tracking_number', $data['record_number'])
            ->select('requests.*', 'pt.name as procedure_name', 'pt.slug as procedure_slug',
                'pt.description as procedure_description',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) as applicant_name"),
                'u.document_type', 'u.document_number')
            ->first();

        $notFound = 'No encontramos un trámite con esos datos. Verifica el número de radicado y documento.';

        if (! $req || $req->document_number !== $data['document_number']
            || (isset($data['document_type']) && $req->document_type !== $data['document_type'])) {
            return response()->json(['error' => $notFound], 404);
        }

        $history = RequestHistory::query()
            ->leftJoin('users as u', 'request_history.user_id', '=', 'u.id')
            ->where('request_history.request_id', $req->id)
            ->orderBy('request_history.created_at')
            ->get(['request_history.*', DB::raw("CONCAT(u.first_name, ' ', u.last_name) as user_name")]);

        $documents = \App\Models\RequestDocument::where('request_id', $req->id)->get();

        // Nota: el backend Node consultaba con request.id (bug); aquí se usa el user_id real.
        $appointment = \App\Models\Appointment::query()
            ->leftJoin('offices as o', 'appointments.office_id', '=', 'o.id')
            ->where('appointments.procedure_type_id', $req->procedure_type_id)
            ->where('appointments.user_id', $req->user_id)
            ->select('appointments.*', 'o.name as office')
            ->first();

        $nextSteps = [
            'borrador' => 'Completa el formulario y radica tu solicitud.',
            'pendiente' => 'Tu solicitud está en espera de revisión inicial.',
            'en_revision' => 'Tu solicitud está siendo evaluada por nuestros funcionarios.',
            'devuelto' => 'Revisa las observaciones y corrige los documentos requeridos.',
            'aprobado' => 'Tu trámite ha sido aprobado. Acércate a la oficina para finalizar.',
            'rechazado' => 'Comunícate con la SGCM para más información sobre el rechazo.',
            'finalizado' => 'Tu trámite ha sido completado exitosamente.',
        ];

        $status = $req->status;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $req->id,
                'record_number' => $req->tracking_number,
                'procedure_name' => $req->procedure_name,
                'procedure_slug' => $req->procedure_slug,
                'applicant_name' => $req->applicant_name,
                'document_type' => $req->document_type,
                'document_number' => $req->document_number,
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status] ?? $status,
                'submitted_at' => $req->submitted_at?->toISOString(),
                'updated_at' => $req->updated_at?->toISOString(),
                'office' => $appointment?->office,
                'next_step' => $nextSteps[$status] ?? 'Espera actualizaciones.',
                'observations' => $req->notes ?? '',
                'appointment' => $appointment ? [
                    'date' => $appointment->scheduled_date,
                    'time' => $appointment->scheduled_time,
                    'office' => $appointment->office,
                ] : null,
                'timeline' => $history->map(fn ($h) => [
                    'status' => $h->to_status ?? $h->action,
                    'description' => $h->comment ?? '',
                    'created_at' => $h->created_at?->toISOString(),
                    'user_name' => $h->user_name,
                ]),
                'documents' => $documents->map(fn ($d) => [
                    'name' => $d->document_type ?: $d->original_name,
                    'status' => $d->is_validated ? 'Recibido' : 'Pendiente',
                    'observation' => null,
                    'uploaded_at' => $d->created_at?->toISOString(),
                    'id' => $d->id,
                    'can_download' => (bool) $d->is_validated,
                ]),
            ],
        ]);
    }

    #[OA\Get(
        path: '/requests/my-procedures',
        summary: 'Obtener los trámites del usuario autenticado',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de trámites del usuario'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function getMyProcedures(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $rows = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->where('requests.user_id', $user->id)
            ->orderByDesc('requests.submitted_at')
            ->get(['requests.*', 'pt.name as procedure_name', 'pt.slug as procedure_slug']);

        $nextSteps = [
            'borrador' => 'Completa el formulario y radica tu solicitud.',
            'pendiente' => 'Tu solicitud está en espera de revisión inicial.',
            'en_revision' => 'Tu solicitud está siendo evaluada.',
            'devuelto' => 'Revisa las observaciones y corrige los documentos.',
            'aprobado' => 'Acércate a la oficina para finalizar.',
            'rechazado' => 'Comunícate con la SGCM para más información.',
            'finalizado' => 'Trámite completado exitosamente.',
        ];

        $summary = [
            'total' => $rows->count(),
            'in_review' => $rows->where('status', 'en_revision')->count(),
            'approved' => $rows->whereIn('status', ['aprobado', 'finalizado'])->count(),
            'pending' => $rows->whereIn('status', ['pendiente', 'borrador'])->count(),
            'observed' => $rows->where('status', 'devuelto')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'record_number' => $r->tracking_number,
                'procedure_name' => $r->procedure_name,
                'procedure_slug' => $r->procedure_slug,
                'status' => $r->status,
                'status_label' => self::STATUS_LABELS[$r->status] ?? $r->status,
                'submitted_at' => $r->submitted_at?->toISOString(),
                'updated_at' => $r->updated_at?->toISOString(),
                'office' => null,
                'next_step' => $nextSteps[$r->status] ?? 'Espera actualizaciones.',
                'icon' => 'document-text-outline',
            ]),
            'summary' => $summary,
        ]);
    }

    #[OA\Get(
        path: '/requests/my-procedures/{id}',
        summary: 'Obtener el detalle de un trámite',
        tags: ['Requests'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del trámite'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin acceso al trámite'),
            new OA\Response(response: 404, description: 'Trámite no encontrado'),
        ]
    )]
    public function getProcedureDetail(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $isStaff = $user->hasRole('admin', 'funcionario');

        $req = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->join('users as u', 'requests.user_id', '=', 'u.id')
            ->leftJoin('users as assigned', 'requests.assigned_to', '=', 'assigned.id')
            ->where('requests.id', $id)
            ->select('requests.*', 'pt.name as procedure_name', 'pt.slug as procedure_slug',
                'pt.description as procedure_description',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) as applicant_name"),
                'u.document_type', 'u.document_number', 'u.email', 'u.phone',
                DB::raw("CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_name"))
            ->first();

        if (! $req) {
            return response()->json(['error' => 'Trámite no encontrado'], 404);
        }

        if ($req->user_id !== $user->id && ! $isStaff) {
            return response()->json(['error' => 'No tienes acceso a este trámite'], 403);
        }

        $history = RequestHistory::query()
            ->leftJoin('users as uh', 'request_history.user_id', '=', 'uh.id')
            ->where('request_history.request_id', $req->id)
            ->orderBy('request_history.created_at')
            ->get(['request_history.*', DB::raw("CONCAT(uh.first_name, ' ', uh.last_name) as user_name")]);

        $documents = \App\Models\RequestDocument::where('request_id', $req->id)->get();

        $appointments = \App\Models\Appointment::query()
            ->leftJoin('offices as o', 'appointments.office_id', '=', 'o.id')
            ->where('appointments.user_id', $req->user_id)
            ->where('appointments.procedure_type_id', $req->procedure_type_id)
            ->orderByDesc('appointments.scheduled_date')
            ->select('appointments.*', 'o.name as office')
            ->get();

        $status = $req->status;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $req->id,
                'record_number' => $req->tracking_number,
                'procedure_name' => $req->procedure_name,
                'procedure_slug' => $req->procedure_slug,
                'procedure_description' => $req->procedure_description,
                'applicant_name' => $req->applicant_name,
                'document_type' => $req->document_type,
                'document_number' => $req->document_number,
                'email' => $req->email,
                'phone' => $req->phone,
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status] ?? $status,
                'submitted_at' => $req->submitted_at?->toISOString(),
                'updated_at' => $req->updated_at?->toISOString(),
                'resolved_at' => $req->resolved_at?->toISOString(),
                'office' => $appointments->first()?->office,
                'assigned_to' => $req->assigned_name,
                'observations' => $req->notes ?? '',
                'actions' => [
                    'can_upload_corrections' => $status === 'devuelto',
                    'can_schedule_appointment' => true,
                    'can_download_receipt' => in_array($status, ['aprobado', 'finalizado']),
                    'can_cancel' => in_array($status, ['pendiente', 'borrador']),
                ],
                'timeline' => $history->map(fn ($h) => [
                    'id' => $h->id,
                    'status' => $h->to_status ?? $h->action,
                    'description' => $h->comment ?? '',
                    'created_at' => $h->created_at?->toISOString(),
                    'user_name' => $h->user_name,
                ]),
                'documents' => $documents->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'mime_type' => $d->mime_type,
                    'size' => $d->file_size,
                    'document_type' => $d->document_type,
                    'is_validated' => (bool) $d->is_validated,
                    'uploaded_at' => $d->created_at?->toISOString(),
                ]),
                'appointments' => $appointments->map(fn ($a) => [
                    'id' => $a->id,
                    'code' => $a->appointment_code,
                    'date' => $a->scheduled_date,
                    'time' => $a->scheduled_time,
                    'status' => $a->status,
                    'office' => $a->office,
                ]),
            ],
        ]);
    }
}
