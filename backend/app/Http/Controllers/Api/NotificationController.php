<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: '/notifications',
        summary: 'Listar notificaciones del usuario autenticado',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de notificaciones'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function getNotifications(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $rows = AppNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'notifications' => $rows->map(fn (AppNotification $n) => [
                'id' => (string) $n->id,
                'title' => $n->title,
                'description' => $n->description,
                'type' => $n->type,
                'read' => (bool) $n->read,
                'date' => $n->created_at?->toISOString(),
            ]),
        ]);
    }

    #[OA\Patch(
        path: '/notifications/{id}/read',
        summary: 'Marcar una notificación como leída',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID de la notificación',
                schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notificación marcada como leída'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        AppNotification::where('id', $id)->where('user_id', $user->id)->update(['read' => true]);

        return response()->json(['message' => 'Notificación marcada como leída']);
    }

    #[OA\Patch(
        path: '/notifications/read-all',
        summary: 'Marcar todas las notificaciones como leídas',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Todas las notificaciones marcadas como leídas'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        AppNotification::where('user_id', $user->id)->update(['read' => true]);

        return response()->json(['message' => 'Todas las notificaciones marcadas como leídas']);
    }

    #[OA\Get(
        path: '/notifications/unread-count',
        summary: 'Obtener el conteo de notificaciones no leídas',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Conteo de notificaciones no leídas'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function getUnreadCount(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $count = AppNotification::where('user_id', $user->id)->where('read', false)->count();

        return response()->json(['count' => $count]);
    }

    #[OA\Delete(
        path: '/notifications/clear-all',
        summary: 'Eliminar todas las notificaciones del usuario',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Notificaciones eliminadas'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function clearAll(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        AppNotification::where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Notificaciones eliminadas']);
    }

    #[OA\Post(
        path: '/notifications/seed',
        summary: 'Crear notificaciones de ejemplo para el usuario',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Notificaciones de ejemplo creadas'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function seedNotifications(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $samples = [
            ['title' => 'Trámite actualizado', 'description' => 'Tu solicitud de tarjeta de residencia ha sido aprobada. Revisa los detalles en Mis trámites.', 'type' => 'success'],
            ['title' => 'Documentos requeridos', 'description' => 'Tu trámite de duplicado requiere documentos adicionales. Ingresa para ver los detalles.', 'type' => 'alert'],
            ['title' => 'Recordatorio de cita', 'description' => 'Tienes una cita programada para el próximo 30 de mayo en la oficina de la SGCM.', 'type' => 'info'],
            ['title' => 'Pago confirmado', 'description' => 'El pago de tu tarjeta de turismo ha sido recibido y está en proceso de validación.', 'type' => 'success'],
            ['title' => 'Cambio en horarios', 'description' => 'La SGCM informa que el horario de atención se modificará durante el mes de junio.', 'type' => 'alert'],
        ];

        foreach ($samples as $sample) {
            AppNotification::create([
                'user_id' => $user->id,
                ...$sample,
                'created_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Notificaciones de ejemplo creadas'], 201);
    }
}
