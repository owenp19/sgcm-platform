<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\RequestsReportExport;
use App\Models\PopulationZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenApi\Attributes as OA;

class AdminController extends Controller
{
    #[OA\Get(
        path: '/admin/dashboard',
        summary: 'Estadísticas del panel de administración',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'dateFrom', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateTo', in: 'query', description: 'Fecha final (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado de la solicitud', schema: new OA\Schema(type: 'string', enum: ['pendiente', 'en_revision', 'aprobado', 'rechazado', 'devuelto', 'finalizado'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas del dashboard'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function getDashboardStats(Request $request): JsonResponse
    {
        $reqQuery = ServiceRequestBase::filtered($request);

        $requestStats = (clone $reqQuery)
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN status = 'en_revision' THEN 1 ELSE 0 END) AS en_revision,
                SUM(CASE WHEN status = 'aprobado' THEN 1 ELSE 0 END) AS aprobados,
                SUM(CASE WHEN status = 'rechazado' THEN 1 ELSE 0 END) AS rechazados,
                SUM(CASE WHEN status = 'devuelto' THEN 1 ELSE 0 END) AS devueltos,
                SUM(CASE WHEN status = 'finalizado' THEN 1 ELSE 0 END) AS finalizados,
                SUM(CASE WHEN status IN ('pendiente','en_revision','devuelto') THEN 1 ELSE 0 END) AS activos,
                SUM(CASE WHEN status IN ('aprobado','rechazado','finalizado') THEN 1 ELSE 0 END) AS resueltos")
            ->first();

        $userStats = DB::table('users as u')
            ->leftJoin('user_roles as ur', 'u.id', '=', 'ur.user_id')
            ->leftJoin('roles as r', 'ur.role_id', '=', 'r.id')
            ->whereIn('r.name', ['ciudadano', 'funcionario', 'admin'])
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN r.name = 'ciudadano' THEN 1 ELSE 0 END) AS ciudadanos,
                SUM(CASE WHEN r.name = 'funcionario' THEN 1 ELSE 0 END) AS funcionarios,
                SUM(CASE WHEN r.name = 'admin' THEN 1 ELSE 0 END) AS admins")
            ->first();

        $recentRequests = ServiceRequestBase::baseSelect()
            ->orderByDesc('r.submitted_at')
            ->limit(10)
            ->get();

        $monthlyStats = DB::table('requests')
            ->where('submitted_at', '>=', now()->subMonths(12))
            ->selectRaw("DATE_FORMAT(submitted_at, '%Y-%m') AS month, COUNT(*) AS total")
            ->groupBy('month')->orderBy('month')->get();

        $topProcedures = DB::table('requests as r')
            ->join('procedure_types as pt', 'r.procedure_type_id', '=', 'pt.id')
            ->selectRaw('pt.name, COUNT(*) AS count')
            ->groupBy('pt.name')->orderByDesc('count')->limit(5)->get();

        $documentStats = DB::table('request_documents')
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN is_validated = 1 THEN 1 ELSE 0 END) AS validados,
                SUM(CASE WHEN is_validated = 0 THEN 1 ELSE 0 END) AS pendientes")
            ->first();

        $appointmentStats = DB::table('appointments')
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN status = 'confirmada' THEN 1 ELSE 0 END) AS confirmadas,
                SUM(CASE WHEN status = 'completada' THEN 1 ELSE 0 END) AS completadas,
                SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) AS canceladas")
            ->first();

        $recentActivity = collect([
            ...DB::table('requests as r')
                ->join('users as u', 'r.user_id', '=', 'u.id')
                ->selectRaw("'request' AS type, r.id, r.tracking_number AS code, r.status, NULL AS action,
                    CONCAT(u.first_name, ' ', u.last_name) AS user_name, r.submitted_at AS created_at")
                ->orderByDesc('r.submitted_at')->limit(5)->get()->all(),
            ...DB::table('request_documents as rd')
                ->join('users as u', 'rd.user_id', '=', 'u.id')
                ->selectRaw("'document' AS type, rd.id, rd.original_name AS code, NULL AS status,
                    'upload' AS action, CONCAT(u.first_name, ' ', u.last_name) AS user_name, rd.created_at")
                ->orderByDesc('rd.created_at')->limit(5)->get()->all(),
            ...DB::table('appointments as a')
                ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
                ->selectRaw("'appointment' AS type, a.id, a.appointment_code AS code, a.status, NULL AS action,
                    COALESCE(a.citizen_full_name, CONCAT(u.first_name, ' ', u.last_name)) AS user_name, a.created_at")
                ->orderByDesc('a.created_at')->limit(5)->get()->all(),
        ])->sortByDesc('created_at')->take(10)->values();

        $documentsByMonth = DB::table('request_documents')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total,
                SUM(CASE WHEN is_validated = 1 THEN 1 ELSE 0 END) AS validados,
                SUM(CASE WHEN is_validated = 0 THEN 1 ELSE 0 END) AS pendientes")
            ->groupBy('month')->orderBy('month')->get();

        $usersByMonth = DB::table('users as u')
            ->leftJoin('user_roles as ur', 'u.id', '=', 'ur.user_id')
            ->leftJoin('roles as r', 'ur.role_id', '=', 'r.id')
            ->where('u.created_at', '>=', now()->subMonths(12))
            ->where(fn ($q) => $q->whereIn('r.name', ['ciudadano', 'funcionario', 'admin'])->orWhereNull('r.name'))
            ->selectRaw("DATE_FORMAT(u.created_at, '%Y-%m') AS month, COUNT(*) AS total,
                SUM(CASE WHEN r.name = 'ciudadano' THEN 1 ELSE 0 END) AS ciudadanos,
                SUM(CASE WHEN r.name IN ('funcionario','admin') THEN 1 ELSE 0 END) AS funcionarios")
            ->groupBy('month')->orderBy('month')->get();

        $appointmentsByDay = DB::table('appointments')
            ->where('scheduled_date', '>=', now()->subDays(30))
            ->selectRaw("scheduled_date AS date, COUNT(*) AS total,
                SUM(CASE WHEN status IN ('pendiente','confirmada') THEN 1 ELSE 0 END) AS ocupados,
                SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) AS cancelados,
                SUM(CASE WHEN status = 'completada' THEN 1 ELSE 0 END) AS completados")
            ->groupBy('date')->orderBy('date')->get();

        $overdueRequests = DB::table('requests')
            ->whereIn('status', ['pendiente', 'en_revision', 'devuelto'])
            ->where('submitted_at', '<', now()->subDays(7))->count('id');

        $pendingDocsAlerts = DB::table('request_documents')
            ->where('is_validated', false)
            ->where('created_at', '<', now()->subDays(5))->count('id');

        $unconfirmedAppointments = DB::table('appointments')
            ->where('status', 'pendiente')
            ->where('scheduled_date', '<', now()->addDay())->count('id');

        $alerts = [];
        if ($requestStats->pendientes > 0) {
            $alerts[] = ['type' => 'warning', 'title' => 'Solicitudes pendientes', 'message' => "{$requestStats->pendientes} solicitudes esperan revisión", 'count' => (int) $requestStats->pendientes, 'link' => '/admin/requests?status=pendiente'];
        }
        if ($documentStats->pendientes > 0) {
            $alerts[] = ['type' => 'warning', 'title' => 'Documentos por validar', 'message' => "{$documentStats->pendientes} documentos pendientes de validación", 'count' => (int) $documentStats->pendientes, 'link' => '/admin/documents?status=pendiente'];
        }
        if ($overdueRequests > 0) {
            $alerts[] = ['type' => 'alert', 'title' => 'Solicitudes vencidas', 'message' => "{$overdueRequests} solicitudes sin respuesta por más de 7 días", 'count' => $overdueRequests, 'link' => '/admin/requests?status=pendiente'];
        }
        if ($requestStats->rechazados > 0) {
            $alerts[] = ['type' => 'danger', 'title' => 'Solicitudes rechazadas', 'message' => "{$requestStats->rechazados} solicitudes han sido rechazadas", 'count' => (int) $requestStats->rechazados, 'link' => '/admin/requests?status=rechazado'];
        }
        if ($pendingDocsAlerts > 0) {
            $alerts[] = ['type' => 'warning', 'title' => 'Documentos sin revisión', 'message' => "{$pendingDocsAlerts} documentos sin validar por más de 5 días", 'count' => $pendingDocsAlerts, 'link' => '/admin/documents?status=pendiente'];
        }
        if ($unconfirmedAppointments > 0) {
            $alerts[] = ['type' => 'info', 'title' => 'Citas por confirmar', 'message' => "{$unconfirmedAppointments} citas próximas sin confirmar", 'count' => $unconfirmedAppointments, 'link' => '/admin/appointments?status=pendiente'];
        }
        if ($appointmentStats->completadas > 0) {
            $alerts[] = ['type' => 'success', 'title' => 'Citas completadas', 'message' => "{$appointmentStats->completadas} citas completadas exitosamente", 'count' => (int) $appointmentStats->completadas, 'link' => '/admin/appointments?status=completada'];
        }

        return response()->json([
            'requests' => $requestStats,
            'users' => $userStats,
            'documents' => $documentStats,
            'appointments' => $appointmentStats,
            'recentRequests' => $recentRequests->map(fn ($r) => [
                'id' => $r->id,
                'trackingNumber' => $r->tracking_number,
                'status' => $r->status,
                'priority' => $r->priority,
                'procedureName' => $r->procedure_name,
                'citizenName' => $r->citizen_name,
                'submittedAt' => $r->submitted_at,
            ]),
            'recentActivity' => $recentActivity->map(fn ($a) => [
                'type' => $a->type,
                'id' => $a->id,
                'code' => $a->code,
                'status' => $a->status,
                'action' => $a->action,
                'userName' => $a->user_name,
                'createdAt' => $a->created_at,
            ]),
            'monthlyStats' => $monthlyStats,
            'topProcedures' => $topProcedures,
            'documentsByMonth' => $documentsByMonth,
            'usersByMonth' => $usersByMonth,
            'appointmentsByDay' => $appointmentsByDay,
            'alerts' => $alerts,
            'lastUpdate' => now()->toISOString(),
            'requestsByStatus' => [
                ['status' => 'pendiente', 'count' => (int) $requestStats->pendientes, 'label' => 'Pendientes'],
                ['status' => 'en_revision', 'count' => (int) $requestStats->en_revision, 'label' => 'En revisión'],
                ['status' => 'aprobado', 'count' => (int) $requestStats->aprobados, 'label' => 'Aprobados'],
                ['status' => 'rechazado', 'count' => (int) $requestStats->rechazados, 'label' => 'Rechazados'],
                ['status' => 'devuelto', 'count' => (int) $requestStats->devueltos, 'label' => 'Devueltos'],
                ['status' => 'finalizado', 'count' => (int) $requestStats->finalizados, 'label' => 'Finalizados'],
            ],
            'documentsByStatus' => [
                ['status' => 'pendientes', 'count' => (int) $documentStats->pendientes, 'label' => 'Pendientes'],
                ['status' => 'validados', 'count' => (int) $documentStats->validados, 'label' => 'Validados'],
            ],
            'activeFuncionarios' => (int) $userStats->funcionarios + (int) $userStats->admins,
        ]);
    }

    #[OA\Get(
        path: '/admin/audit-log',
        summary: 'Consultar el registro de auditoría',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: 'Número de página', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad por página', schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'search', in: 'query', description: 'Término de búsqueda', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'dateFrom', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateTo', in: 'query', description: 'Fecha final (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'action', in: 'query', description: 'Acción a filtrar', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'entityType', in: 'query', description: 'Tipo de entidad a filtrar', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de logs de auditoría con paginación'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function getAuditLog(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $limit = max(1, (int) $request->query('limit', '50'));

        $query = DB::table('audit_log as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.id');

        if ($dateFrom = $request->query('dateFrom')) {
            $query->where('al.created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('dateTo')) {
            $query->where('al.created_at', '<=', $dateTo);
        }
        if ($action = $request->query('action')) {
            $query->where('al.action', $action);
        }
        if ($entityType = $request->query('entityType')) {
            $query->where('al.entity_type', $entityType);
        }

        $total = (clone $query)->count();

        $rows = $query->orderByDesc('al.created_at')
            ->forPage($page, $limit)
            ->get(['al.*', DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS user_name")]);

        return response()->json([
            'logs' => $rows->map(fn ($l) => [
                'id' => $l->id,
                'userId' => $l->user_id,
                'userName' => $l->user_name,
                'action' => $l->action,
                'entityType' => $l->entity_type,
                'entityId' => $l->entity_id,
                'details' => $l->details ? json_decode($l->details, true) : null,
                'ipAddress' => $l->ip_address,
                'createdAt' => $l->created_at,
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
        path: '/admin/reports',
        summary: 'Generar reporte de solicitudes agrupado',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'groupBy', in: 'query', description: 'Criterio de agrupación', schema: new OA\Schema(type: 'string', enum: ['procedure', 'status', 'funcionario', 'date'])),
            new OA\Parameter(name: 'dateFrom', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateTo', in: 'query', description: 'Fecha final (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte de solicitudes'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function getReports(Request $request): JsonResponse
    {
        $groupBy = $request->query('groupBy');

        [$groupField, $selectField] = match ($groupBy) {
            'procedure' => ['pt.name', 'pt.name AS label'],
            'status' => ['r.status', 'r.status AS label'],
            'funcionario' => ["CONCAT(assigned.first_name, ' ', assigned.last_name)", "CONCAT(assigned.first_name, ' ', assigned.last_name) AS label"],
            default => ['DATE(r.submitted_at)', 'DATE(r.submitted_at) AS label'],
        };

        $query = DB::table('requests as r')
            ->join('procedure_types as pt', 'r.procedure_type_id', '=', 'pt.id')
            ->leftJoin('users as assigned', 'r.assigned_to', '=', 'assigned.id');

        if ($dateFrom = $request->query('dateFrom')) {
            $query->where('r.submitted_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('dateTo')) {
            $query->where('r.submitted_at', '<=', $dateTo);
        }

        $rows = $query
            ->selectRaw("$selectField, COUNT(*) AS count,
                SUM(CASE WHEN r.status IN ('aprobado','finalizado') THEN 1 ELSE 0 END) AS completed,
                ROUND(AVG(CASE WHEN r.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, r.submitted_at, r.resolved_at) END), 1) AS avg_hours")
            ->groupByRaw($groupField)
            ->orderByDesc('count')
            ->limit(50)
            ->get();

        return response()->json(['reports' => $rows]);
    }

    #[OA\Get(
        path: '/admin/export/excel',
        summary: 'Exportar solicitudes a Excel',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'dateFrom', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateTo', in: 'query', description: 'Fecha final (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado de la solicitud', schema: new OA\Schema(type: 'string', enum: ['pendiente', 'en_revision', 'aprobado', 'rechazado', 'devuelto', 'finalizado'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel (reporte_occre.xlsx)'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function exportExcel(Request $request)
    {
        $rows = ServiceRequestBase::exportRows($request);

        return Excel::download(new RequestsReportExport($rows), 'reporte_occre.xlsx');
    }

    #[OA\Get(
        path: '/admin/export/pdf',
        summary: 'Exportar solicitudes a PDF',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'dateFrom', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateTo', in: 'query', description: 'Fecha final (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado de la solicitud', schema: new OA\Schema(type: 'string', enum: ['pendiente', 'en_revision', 'aprobado', 'rechazado', 'devuelto', 'finalizado'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF (reporte_occre.pdf)'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function exportPdf(Request $request)
    {
        $rows = ServiceRequestBase::exportRows($request, limit: 500);

        $pdf = Pdf::loadView('admin.reporte', ['rows' => $rows, 'generatedAt' => now()])
            ->setPaper('a4');

        return $pdf->download('reporte_occre.pdf');
    }

    #[OA\Get(
        path: '/admin/funcionarios',
        summary: 'Listar funcionarios y administradores',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado de funcionarios'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function getFuncionarios(): JsonResponse
    {
        $rows = DB::table('users as u')
            ->join('user_roles as ur', 'u.id', '=', 'ur.user_id')
            ->join('roles as r', 'ur.role_id', '=', 'r.id')
            ->whereIn('r.name', ['funcionario', 'admin'])
            ->orderBy('u.first_name')
            ->distinct()
            ->get(['u.id', 'u.first_name', 'u.last_name', 'u.email']);

        return response()->json([
            'funcionarios' => $rows->map(fn ($f) => [
                'id' => $f->id,
                'name' => "{$f->first_name} {$f->last_name}",
                'email' => $f->email,
            ]),
        ]);
    }

    #[OA\Get(
        path: '/admin/population-map',
        summary: 'Zonas del mapa de población de San Andrés',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Zonas con datos de población raizal y residentes'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos'),
        ]
    )]
    public function getPopulationMap(): JsonResponse
    {
        $zones = PopulationZone::query()
            ->active()
            ->orderBy('name')
            ->get();

        $totals = (object) [
            'total' => $zones->sum('total_population'),
            'raizal' => $zones->sum('raizal_population'),
            'other' => $zones->sum('other_population'),
            'zones' => $zones->count(),
        ];

        return response()->json([
            'zones' => $zones->map(fn ($z) => [
                'id' => $z->id,
                'name' => $z->name,
                'zone_type' => $z->zone_type,
                'latitude' => $z->latitude,
                'longitude' => $z->longitude,
                'total_population' => $z->total_population,
                'raizal_population' => $z->raizal_population,
                'other_population' => $z->other_population,
                'color' => $z->color,
                'description' => $z->description,
            ]),
            'totals' => $totals,
        ]);
    }
}
