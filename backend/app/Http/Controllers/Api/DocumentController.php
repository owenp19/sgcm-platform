<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RequestDocument;
use App\Models\RequestHistory;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class DocumentController extends Controller
{
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private function storeFile(\Illuminate\Http\UploadedFile $file): array
    {
        $filename = \Illuminate\Support\Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = 'uploads/'.$filename;

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return [$filename, $path];
    }

    #[OA\Post(
        path: '/documents/upload',
        summary: 'Subir un documento a una solicitud',
        tags: ['Documents'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['requestId', 'file'],
                    properties: [
                        new OA\Property(property: 'requestId', type: 'integer'),
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'documentType', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Documento subido exitosamente'),
            new OA\Response(response: 400, description: 'Solicitud o archivo inválido'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos para subir documentos'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
        ]
    )]
    public function upload(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $request->filled('requestId')) {
            return response()->json(['error' => 'El ID de la solicitud es obligatorio'], 400);
        }

        if (! $request->hasFile('file')) {
            return response()->json(['error' => 'El archivo es obligatorio'], 400);
        }

        $file = $request->file('file');

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            return response()->json(['error' => 'Tipo de archivo no permitido. Solo PDF, JPG, PNG y DOC'], 400);
        }

        if ($file->getSize() > (int) config('services.uploads.max_file_size', 10485760)) {
            return response()->json(['error' => 'El archivo excede el tamaño máximo permitido (10MB)'], 400);
        }

        $req = ServiceRequest::find($request->input('requestId'));
        if (! $req) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        if ($req->user_id !== $user->id && ! $user->hasRole('admin')) {
            return response()->json(['error' => 'No tienes permisos para subir documentos a esta solicitud'], 403);
        }

        $documentType = $request->input('documentType', 'general');
        [$storedName, $path] = $this->storeFile($file);

        $document = RequestDocument::create([
            'request_id' => $req->id,
            'user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_path' => $path,
            'document_type' => $documentType,
            'created_at' => now(),
        ]);

        RequestHistory::create([
            'request_id' => $req->id,
            'user_id' => $user->id,
            'action' => 'document_uploaded',
            'comment' => 'Documento subido: '.$file->getClientOriginalName(),
            'created_at' => now(),
        ]);

        AuditLog::record($user->id, 'upload_document', 'request_document', $document->id, [
            'requestId' => $req->id,
            'fileName' => $file->getClientOriginalName(),
        ]);

        return response()->json([
            'message' => 'Documento subido exitosamente',
            'document' => [
                'id' => $document->id,
                'name' => $document->original_name,
                'mimeType' => $document->mime_type,
                'size' => $document->file_size,
                'documentType' => $documentType,
            ],
        ], 201);
    }

    #[OA\Get(
        path: '/documents/{id}/download',
        summary: 'Descargar un documento',
        tags: ['Documents'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo descargado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin acceso al documento'),
            new OA\Response(response: 404, description: 'Documento o archivo no encontrado'),
        ]
    )]
    public function download(Request $request, int $id)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $doc = RequestDocument::query()
            ->join('requests as r', 'request_documents.request_id', '=', 'r.id')
            ->where('request_documents.id', $id)
            ->select('request_documents.*', 'r.user_id as request_owner_id')
            ->first();

        if (! $doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if ($doc->request_owner_id !== $user->id && ! $user->hasRole('admin', 'funcionario')) {
            return response()->json(['error' => 'No tienes acceso a este documento'], 403);
        }

        if (! Storage::disk('local')->exists($doc->file_path)) {
            return response()->json(['error' => 'Archivo no encontrado en el servidor'], 404);
        }

        return Storage::disk('local')->download($doc->file_path, $doc->original_name);
    }

    #[OA\Patch(
        path: '/documents/{id}/validate',
        summary: 'Validar o rechazar un documento',
        tags: ['Documents'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['isValid'],
                properties: [
                    new OA\Property(property: 'isValid', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Documento validado o rechazado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos para validar'),
            new OA\Response(response: 404, description: 'Documento no encontrado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function validateDocument(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['isValid' => ['required', 'boolean']]);

        $doc = RequestDocument::find($id);
        if (! $doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $doc->update([
            'is_validated' => $data['isValid'],
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ]);

        AuditLog::record($request->user()->id, 'validate_document', 'request_document', $id, [
            'isValid' => $data['isValid'],
        ]);

        return response()->json([
            'message' => $data['isValid'] ? 'Documento validado' : 'Documento rechazado',
        ]);
    }

    /**
     * Mejora de seguridad respecto al backend Node: se verifica que el usuario
     * sea dueño del documento o staff antes de eliminarlo.
     */
    #[OA\Delete(
        path: '/documents/{id}',
        summary: 'Eliminar un documento',
        tags: ['Documents'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Documento eliminado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos para eliminar'),
            new OA\Response(response: 404, description: 'Documento no encontrado'),
        ]
    )]
    public function delete(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $doc = RequestDocument::query()
            ->join('requests as r', 'request_documents.request_id', '=', 'r.id')
            ->where('request_documents.id', $id)
            ->select('request_documents.*', 'r.user_id as request_owner_id')
            ->first();

        if (! $doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if ($doc->request_owner_id !== $user->id && ! $user->hasRole('admin', 'funcionario')) {
            return response()->json(['error' => 'No tienes permisos para eliminar este documento'], 403);
        }

        if (Storage::disk('local')->exists($doc->file_path)) {
            Storage::disk('local')->delete($doc->file_path);
        }

        RequestDocument::where('id', $id)->delete();

        AuditLog::record($user->id, 'delete_document', 'request_document', $id);

        return response()->json(['message' => 'Documento eliminado']);
    }
}
