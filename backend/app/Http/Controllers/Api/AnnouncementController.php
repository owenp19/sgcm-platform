<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class AnnouncementController extends Controller
{
    #[OA\Get(
        path: '/announcements/active',
        summary: 'Listar comunicados activos publicados',
        tags: ['Announcements'],
        responses: [
            new OA\Response(response: 200, description: 'Lista de comunicados activos'),
        ]
    )]
    public function getActive(): JsonResponse
    {
        // Endpoint público y consultado con frecuencia. Cache 2 minutos.
        $announcements = Cache::remember('announcements.active', 120, function () {
            return Announcement::query()
                ->where('is_active', true)
                ->where('published_at', '<=', now())
                ->orderByDesc('published_at')
                ->get(['id', 'title', 'body', 'type', 'published_at']);
        });

        return response()->json(['announcements' => $announcements]);
    }

    #[OA\Get(
        path: '/announcements',
        summary: 'Listar todos los comunicados',
        tags: ['Announcements'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de comunicados'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function getAll(): JsonResponse
    {
        $announcements = Announcement::query()
            ->orderByDesc('published_at')
            ->get(['id', 'title', 'body', 'type', 'is_active', 'published_at', 'created_at']);

        return response()->json(['announcements' => $announcements]);
    }

    #[OA\Post(
        path: '/announcements',
        summary: 'Crear un nuevo comunicado',
        tags: ['Announcements'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'body'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Aviso importante'),
                    new OA\Property(property: 'body', type: 'string', example: 'Contenido del comunicado'),
                    new OA\Property(property: 'type', type: 'string', nullable: true, enum: ['info', 'alert', 'important'], example: 'info'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Comunicado creado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['nullable', 'in:info,alert,important'],
        ]);

        $announcement = Announcement::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'type' => in_array($data['type'] ?? 'info', ['info', 'alert', 'important']) ? ($data['type'] ?? 'info') : 'info',
            'is_active' => true,
            'published_at' => now(),
            'created_at' => now(),
        ]);

        AuditLog::record($request->user()->id, 'Crear comunicado', 'announcement', $announcement->id, [
            'title' => $data['title'],
        ]);

        Cache::forget('announcements.active');

        return response()->json(['message' => 'Comunicado creado', 'id' => $announcement->id], 201);
    }

    #[OA\Put(
        path: '/announcements/{id}',
        summary: 'Actualizar un comunicado',
        tags: ['Announcements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID del comunicado',
                schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, nullable: true, example: 'Aviso actualizado'),
                    new OA\Property(property: 'body', type: 'string', nullable: true, example: 'Contenido actualizado'),
                    new OA\Property(property: 'type', type: 'string', nullable: true, enum: ['info', 'alert', 'important'], example: 'important'),
                    new OA\Property(property: 'isActive', type: 'boolean', nullable: true, example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Comunicado actualizado'),
            new OA\Response(response: 400, description: 'No hay campos para actualizar'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
            new OA\Response(response: 404, description: 'Comunicado no encontrado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'type' => ['sometimes', 'in:info,alert,important'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        if (empty($data)) {
            return response()->json(['error' => 'No hay campos para actualizar'], 400);
        }

        $announcement = Announcement::findOrFail($id);
        $announcement->fill([
            'title' => $data['title'] ?? $announcement->title,
            'body' => $data['body'] ?? $announcement->body,
            'type' => $data['type'] ?? $announcement->type,
            'is_active' => array_key_exists('isActive', $data) ? (int) $data['isActive'] : $announcement->is_active,
            'published_at' => now(),
        ])->save();

        AuditLog::record($request->user()->id, 'Actualizar comunicado', 'announcement', $id, [
            'title' => $data['title'] ?? null,
        ]);

        Cache::forget('announcements.active');

        return response()->json(['message' => 'Comunicado actualizado']);
    }

    #[OA\Delete(
        path: '/announcements/{id}',
        summary: 'Eliminar un comunicado',
        tags: ['Announcements'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID del comunicado',
                schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Comunicado eliminado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador'),
        ]
    )]
    public function remove(Request $request, int $id): JsonResponse
    {
        Announcement::where('id', $id)->delete();

        AuditLog::record($request->user()->id, 'Eliminar comunicado', 'announcement', $id);

        Cache::forget('announcements.active');

        return response()->json(['message' => 'Comunicado eliminado']);
    }
}
