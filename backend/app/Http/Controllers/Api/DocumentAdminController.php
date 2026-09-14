<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DocumentAdminController extends Controller
{
    #[OA\Get(
        path: '/admin/documents',
        summary: 'Listar documentos de todas las solicitudes',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: 'Número de página', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad por página', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por nombre, ciudadano, correo o número de radicado', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado de validación', schema: new OA\Schema(type: 'string', enum: ['validated', 'pending'])),
            new OA\Parameter(name: 'documentType', in: 'query', description: 'Filtrar por tipo de documento', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'dateFrom', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateTo', in: 'query', description: 'Fecha final (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'userId', in: 'query', description: 'Filtrar por usuario', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de documentos con paginación'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function getAllDocuments(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $limit = max(1, (int) $request->query('limit', '20'));

        $query = DB::table('request_documents as rd')
            ->join('users as u', 'rd.user_id', '=', 'u.id')
            ->leftJoin('requests as r', 'rd.request_id', '=', 'r.id');

        if ($search = $request->query('search')) {
            $term = "%{$search}%";
            $query->where(fn ($q) => $q
                ->where('rd.original_name', 'like', $term)
                ->orWhereRaw("CONCAT(u.first_name, ' ', u.last_name) LIKE ?", [$term])
                ->orWhere('u.email', 'like', $term)
                ->orWhere('r.tracking_number', 'like', $term));
        }
        match ($request->query('status')) {
            'validated' => $query->where('rd.is_validated', true),
            'pending' => $query->where('rd.is_validated', false),
            default => null,
        };
        if ($documentType = $request->query('documentType')) {
            $query->where('rd.document_type', $documentType);
        }
        if ($dateFrom = $request->query('dateFrom')) {
            $query->where('rd.created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('dateTo')) {
            $query->where('rd.created_at', '<=', $dateTo);
        }
        if ($userId = $request->query('userId')) {
            $query->where('rd.user_id', $userId);
        }

        $total = (clone $query)->count();

        $rows = $query->orderByDesc('rd.created_at')
            ->forPage($page, $limit)
            ->get([
                'rd.id', 'rd.original_name', 'rd.mime_type', 'rd.file_size', 'rd.document_type',
                'rd.is_validated', 'rd.created_at', 'rd.file_path',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS user_name"),
                'u.email AS user_email',
                'r.tracking_number', 'r.status AS request_status',
            ]);

        return response()->json([
            'documents' => $rows->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->original_name,
                'mimeType' => $d->mime_type,
                'size' => $d->file_size,
                'documentType' => $d->document_type,
                'isValidated' => (bool) $d->is_validated,
                'userName' => $d->user_name,
                'userEmail' => $d->user_email,
                'trackingNumber' => $d->tracking_number,
                'requestStatus' => $d->request_status,
                'filePath' => $d->file_path,
                'createdAt' => $d->created_at,
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
        path: '/admin/documents/stats',
        summary: 'Estadísticas de documentos (total, validados, pendientes)',
        tags: ['Admin'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas de documentos'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function getAllDocumentsStats(): JsonResponse
    {
        $stats = DB::table('request_documents')
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN is_validated = 1 THEN 1 ELSE 0 END) AS validados,
                SUM(CASE WHEN is_validated = 0 THEN 1 ELSE 0 END) AS pendientes")
            ->first();

        return response()->json($stats);
    }
}
