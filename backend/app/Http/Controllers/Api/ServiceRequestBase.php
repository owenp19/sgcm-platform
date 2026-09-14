<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Consultas compartidas de solicitudes para dashboard/reportes/exports.
 */
class ServiceRequestBase
{
    public static function filtered(Request $request)
    {
        $query = DB::table('requests as r');

        if ($dateFrom = $request->query('dateFrom')) {
            $query->where('r.submitted_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('dateTo')) {
            $query->where('r.submitted_at', '<=', $dateTo);
        }
        if ($status = $request->query('status')) {
            $query->where('r.status', $status);
        }

        return $query;
    }

    public static function baseSelect()
    {
        return DB::table('requests as r')
            ->join('procedure_types as pt', 'r.procedure_type_id', '=', 'pt.id')
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->selectRaw("r.id, r.tracking_number, r.status, r.priority, r.submitted_at,
                pt.name AS procedure_name,
                CONCAT(u.first_name, ' ', u.last_name) AS citizen_name");
    }
    public static function exportRows(Request $request, int $limit = 0)
    {
        $query = DB::table('requests as r')
            ->join('procedure_types as pt', 'r.procedure_type_id', '=', 'pt.id')
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->leftJoin('users as assigned', 'r.assigned_to', '=', 'assigned.id')
            ->selectRaw("r.tracking_number, r.status, r.priority, r.submitted_at, r.resolved_at,
                pt.name AS procedure_name,
                CONCAT(u.first_name, ' ', u.last_name) AS citizen_name, u.email, u.document_number,
                CONCAT(assigned.first_name, ' ', assigned.last_name) AS assigned_name");

        if ($dateFrom = $request->query('dateFrom')) {
            $query->where('r.submitted_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('dateTo')) {
            $query->where('r.submitted_at', '<=', $dateTo);
        }
        if ($status = $request->query('status')) {
            $query->where('r.status', $status);
        }

        $query->orderByDesc('r.submitted_at');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(fn ($r) => [
            'tracking_number' => $r->tracking_number,
            'procedure_name' => $r->procedure_name,
            'citizen_name' => $r->citizen_name,
            'email' => $r->email,
            'document_number' => $r->document_number,
            'status' => $r->status,
            'priority' => $r->priority,
            'assigned_name' => $r->assigned_name,
            'submitted_at' => $r->submitted_at ? date('d/m/Y H:i', strtotime($r->submitted_at)) : '',
            'resolved_at' => $r->resolved_at ? date('d/m/Y H:i', strtotime($r->resolved_at)) : '',
        ])->all();
    }
}
