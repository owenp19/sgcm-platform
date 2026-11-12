<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Services\RagKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ChatController extends Controller
{
    public function __construct(
        private readonly GeminiService $gemini,
        private readonly RagKnowledgeService $rag,
    ) {
    }

    #[OA\Post(
        path: '/chat',
        summary: 'Enviar mensaje al asistente virtual',
        tags: ['Chat'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', maxLength: 4000),
                    new OA\Property(
                        property: 'context',
                        type: 'string',
                        maxLength: 12000,
                        description: 'Contexto parcial (respuesta FAQ local) enviado por la app.',
                    ),
                    new OA\Property(
                        property: 'history',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            type: 'object',
                            required: ['role', 'text'],
                            properties: [
                                new OA\Property(property: 'role', type: 'string'),
                                new OA\Property(property: 'text', type: 'string'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Respuesta del asistente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
            new OA\Response(response: 502, description: 'Error de conexión con el asistente'),
            new OA\Response(response: 503, description: 'Asistente aún no configurado'),
        ]
    )]
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'context' => ['nullable', 'string', 'max:12000'],
            'history' => ['nullable', 'array'],
            'history.*.role' => ['required_with:history', 'string'],
            'history.*.text' => ['required_with:history', 'string'],
        ]);

        $ragContext = $this->rag->buildContext($data['message'], 4);

        $contextParts = array_values(array_filter([
            ($data['context'] ?? null) !== null && trim($data['context']) !== '' ? trim($data['context']) : null,
            $ragContext !== '' ? $ragContext : null,
        ]));

        $context = $contextParts !== [] ? implode("\n\n", $contextParts) : null;

        try {
            $answer = $this->gemini->chat($data['message'], $data['history'] ?? [], $context);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();

            if (str_starts_with($msg, 'El asistente aún no está configurado')) {
                return response()->json(['error' => $msg], 503);
            }

            return response()->json(['error' => $msg], 502);
        } catch (\Throwable $e) {
            Log::error('[Chat] Error de conexión con el asistente');

            return response()->json(['error' => 'Error de conexión con el asistente. Intenta de nuevo.'], 502);
        }

        return response()->json(['answer' => $answer]);
    }
}