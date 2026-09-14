<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DeviceController extends Controller
{
    #[OA\Post(
        path: '/devices/register',
        summary: 'Registrar un dispositivo para notificaciones push',
        tags: ['Devices'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'platform'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', maxLength: 500, example: 'expo-push-token'),
                    new OA\Property(property: 'platform', type: 'string', enum: ['android', 'ios', 'web'], example: 'android'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Dispositivo registrado exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:500'],
            'platform' => ['required', 'in:android,ios,web'],
        ]);

        DeviceToken::updateOrCreate(
            ['user_id' => $user->id, 'token' => $data['token']],
            ['platform' => $data['platform'], 'is_active' => true]
        );

        return response()->json(['message' => 'Dispositivo registrado exitosamente']);
    }

    #[OA\Post(
        path: '/devices/unregister',
        summary: 'Desregistrar un dispositivo de notificaciones push',
        tags: ['Devices'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', maxLength: 500, example: 'expo-push-token'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Dispositivo desregistrado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function unregister(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate(['token' => ['required', 'string', 'max:500']]);

        DeviceToken::where('user_id', $user->id)
            ->where('token', $data['token'])
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Dispositivo desregistrado']);
    }

    #[OA\Get(
        path: '/devices/my',
        summary: 'Listar dispositivos registrados del usuario',
        tags: ['Devices'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de dispositivos registrados'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function myDevices(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id', 'token', 'platform', 'is_active', 'created_at']);

        return response()->json(['tokens' => $tokens]);
    }
}
