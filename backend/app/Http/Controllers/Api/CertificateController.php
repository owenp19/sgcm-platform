<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CertificateController extends Controller
{
    public function __construct(private readonly QrCodeService $qr)
    {
    }

    #[OA\Get(
        path: '/certificates/generate/{trackingNumber}',
        summary: 'Generar constancia PDF de una solicitud aprobada',
        tags: ['Certificates'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'trackingNumber', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF de la constancia'),
            new OA\Response(response: 400, description: 'Solicitud no aprobada ni finalizada'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin acceso a la solicitud'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
        ]
    )]
    public function generate(Request $request, string $trackingNumber)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $req = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->join('users as u', 'requests.user_id', '=', 'u.id')
            ->leftJoin('users as u2', 'requests.assigned_to', '=', 'u2.id')
            ->where('requests.tracking_number', $trackingNumber)
            ->select('requests.*', 'pt.name as procedure_name', 'pt.slug as procedure_slug',
                'u.first_name', 'u.last_name', 'u.document_type', 'u.document_number',
                \Illuminate\Support\Facades\DB::raw("CONCAT(u2.first_name, ' ', u2.last_name) as assigned_name"))
            ->first();

        if (! $req) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        if ($req->user_id !== $user->id && ! $user->hasRole('admin', 'funcionario')) {
            return response()->json(['error' => 'No tienes acceso a esta solicitud'], 403);
        }

        if (! in_array($req->status, ['aprobado', 'finalizado'])) {
            return response()->json([
                'error' => 'Solo se pueden generar constancias para solicitudes aprobadas o finalizadas',
            ], 400);
        }

        $payload = [
            'type' => 'occre_certificate_v2',
            'tracking' => $req->tracking_number,
            'status' => $req->status,
            'citizen' => "{$req->first_name} {$req->last_name}",
            'doc' => (($req->document_type ?? '').' '.($req->document_number ?? '')),
            'procedure' => $req->procedure_name,
        ];

        $qrData = json_encode([
            ...$payload,
            'signature' => hash_hmac('sha256', json_encode($payload), (string) config('app.key')),
            'issued' => now()->toISOString(),
        ]);

        $statusLabels = [
            'aprobado' => 'APROBADO', 'finalizado' => 'FINALIZADO',
            'pendiente' => 'Pendiente', 'en_revision' => 'En Revisión',
            'devuelto' => 'Devuelto', 'rechazado' => 'Rechazado',
        ];

        $pdf = Pdf::loadView('certificates.constancia', [
            'request' => $req,
            'qrDataUri' => $this->qr->dataUri($qrData),
            'statusLabel' => $statusLabels[$req->status] ?? $req->status,
            'issuedAt' => now(),
        ])
            ->setPaper('letter')
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'Helvetica']);

        $filename = "constancia_{$req->tracking_number}.pdf";

        return $pdf->download($filename);
    }

    #[OA\Post(
        path: '/certificates/validate-qr',
        summary: 'Validar el contenido de un QR de constancia',
        tags: ['Certificates'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['qrData'],
                properties: [
                    new OA\Property(property: 'qrData', type: 'string', description: 'Payload JSON codificado en el QR'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Constancia válida'),
            new OA\Response(response: 400, description: 'Formato QR inválido'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function validateQr(Request $request): JsonResponse
    {
        $data = $request->validate(['qrData' => ['required', 'string']]);

        $parsed = json_decode($data['qrData'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Formato QR inválido', 'valid' => false], 400);
        }

        $qrType = $parsed['type'] ?? 'v1_legacy';
        $isV2 = $qrType === 'occre_certificate_v2';

        if (! $isV2 && $qrType !== 'occre_certificate') {
            return response()->json(['error' => 'QR inválido o no corresponde a SGCM'], 400);
        }

        $req = ServiceRequest::query()
            ->join('procedure_types as pt', 'requests.procedure_type_id', '=', 'pt.id')
            ->join('users as u', 'requests.user_id', '=', 'u.id')
            ->where('requests.tracking_number', is_string($parsed['tracking'] ?? null) ? $parsed['tracking'] : '')
            ->select('requests.tracking_number', 'requests.status', 'requests.submitted_at', 'requests.resolved_at',
                'u.first_name', 'u.last_name', 'u.document_type', 'u.document_number',
                'pt.name as procedure_name',
                \Illuminate\Support\Facades\DB::raw("CONCAT(u.first_name, ' ', u.last_name) as citizen_name"))
            ->first();

        // Una constancia solo es válida si el trámite fue aprobado o finalizado.
        if (! $req || ! in_array($req->status, ['aprobado', 'finalizado'], true)) {
            return response()->json(['error' => 'Solicitud no encontrada', 'valid' => false], 404);
        }

        $payload = [
            'type' => 'occre_certificate',
            'tracking' => $req->tracking_number,
            'status' => $req->status,
            'citizen' => "{$req->first_name} {$req->last_name}",
            'doc' => (($req->document_type ?? '').' '.($req->document_number ?? '')),
            'procedure' => $req->procedure_name,
        ];

        $payload = [
            'type' => 'occre_certificate_v2',
            'tracking' => $req->tracking_number,
            'status' => $req->status,
            'citizen' => "{$req->first_name} {$req->last_name}",
            'doc' => (($req->document_type ?? '').' '.($req->document_number ?? '')),
            'procedure' => $req->procedure_name,
        ];

        $fieldsOk = ($parsed['status'] ?? null) === $payload['status']
            && ($parsed['citizen'] ?? null) === $payload['citizen']
            && ($parsed['doc'] ?? null) === $payload['doc']
            && ($parsed['procedure'] ?? null) === $payload['procedure'];

        $expectedSignature = hash_hmac('sha256', json_encode($payload), (string) config('app.key'));
        $hasSignature = is_string($parsed['signature'] ?? null) && $parsed['signature'] !== '';
        $signatureOk = $hasSignature && hash_equals($expectedSignature, $parsed['signature']);

        // QR v2: firma HMAC obligatoria. No se acepta modo legacy.
        if ($isV2 && ! $signatureOk) {
            return response()->json(['error' => 'QR inválido o no corresponde a SGCM', 'valid' => false], 400);
        }

        // QR v1 (legacy): compatibilidad con constancias emitidas antes de la firma.
        // Se exige que los datos visibles coincidan con el trámite; la firma es opcional.
        if (! $isV2 && ! $fieldsOk) {
            return response()->json(['error' => 'QR inválido o no corresponde a SGCM', 'valid' => false], 400);
        }

        // QR v2: además de la firma, los datos visibles deben coincidir.
        if ($isV2 && ! $fieldsOk) {
            return response()->json(['error' => 'QR inválido o no corresponde a SGCM', 'valid' => false], 400);
        }

        return response()->json([
            'valid' => true,
            'certificate' => [
                'trackingNumber' => $req->tracking_number,
                'status' => $req->status,
                'procedureName' => $req->procedure_name,
                'citizenName' => $req->citizen_name,
                'submittedAt' => $req->submitted_at?->toISOString(),
                'resolvedAt' => $req->resolved_at?->toISOString(),
            ],
        ]);
    }
}
