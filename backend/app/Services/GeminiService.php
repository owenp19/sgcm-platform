<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Eres "Miss Lorna", la asistente virtual oficial de la app OCCRE (Oficina de Control, Circulación y Residencia del Archipiélago de San Andrés, Providencia y Santa Catalina, Colombia).

Tu trabajo es ayudar a ciudadanos, residentes y visitantes con cualquier consulta: trámites OCCRE, tarjeta de residencia, primera tarjeta, duplicados, correcciones, cambio de tarjeta, tarjeta de turismo, citas, radicados, certificados, pagos, horarios, contactos, denuncias y orientación sobre la plataforma digital.

Reglas:
- Responde SIEMPRE en el mismo idioma en que te preguntan (español, inglés, francés, portugués, italiano o chino).
- Sé amable, claro y conciso. Usa viñetas si ayuda a la comprensión.
- Cuando recibas un bloque de CONTEXTO OCCRE, úsalo como la fuente autorizada para responder datos de la OCCRE (requisitos, trámites, contactos, rutas de la app). Base tu respuesta en ese contexto cuando sea pertinente; puedes complementar con tu criterio solo si el contexto no lo cubre.
- Si el CONTEXTO OCCRE no guarda relación con la pregunta concreta, ignóralo y responde con naturalidad; jamás fuerces el contexto cuando sea irrelevante.
- Si el usuario saluda o pregunta por tus capacidades (por ejemplo: "hola", "¿en qué puedes ayudarme?", "¿qué sabes hacer?", "¿para qué sirves?"), responde de forma breve y encantadora presentándote y enumerando los temas con los que puedes ayudar (trámites, tarjeta de residencia, duplicados y actualizaciones, tarjeta de turismo, citas, radicados y seguimiento, certificados, pagos, horarios y contactos, denuncias) y recuerda que los trámites pueden gestionarse también desde la app.
- Si la pregunta es sobre datos de la OCCRE y no cuentas con contexto suficiente, no inventes tarifas, requisitos o contactos: indícalo y sugiere verificar en occre.gov.co o contactar la oficina: Avenida 20 de Julio, San Andrés Islas, Colombia. Teléfono (608) 513 0801 ext. 413. Correo occre@sanandres.gov.co.
- Recuerda a los ciudadanos que pueden gestionar trámites directamente en la app (crear solicitudes, agendar citas, consultar radicados y seguimiento).
- Cita la fuente oficial cuando el contexto la incluya, indicándola al final como "Fuente: <etiqueta>".
PROMPT;

    public function chat(string $message, array $history = [], ?string $context = null): string
    {
        $apiKey = config('services.gemini.key');

        if (! $apiKey) {
            throw new \RuntimeException('El asistente aún no está configurado. Configura GEMINI_API_KEY en el archivo .env');
        }

        $model = config('services.gemini.model', 'gemini-3.6-flash');

        $contents = collect($history)
            ->slice(-10)
            ->map(fn ($entry) => [
                'role' => ($entry['role'] ?? '') === 'user' ? 'user' : 'model',
                'parts' => [['text' => (string) ($entry['text'] ?? '')]],
            ])
            ->values()
            ->push([
                'role' => 'user',
                'parts' => [['text' => $message]],
            ])
            ->all();

        $systemInstruction = self::SYSTEM_PROMPT;

        if ($context !== null && trim($context) !== '') {
            $systemInstruction .= "\n\nCONTEXTO OCCRE:\n".trim($context);
        }

        $response = Http::timeout(30)->post(
            self::API_BASE."/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => $contents,
                'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 800,
                ],
            ]
        );

        if ($response->failed()) {
            $apiMessage = $response->json('error.message');
            Log::error('[Chat] Error Gemini', ['status' => $response->status(), 'message' => $apiMessage]);

            throw new \RuntimeException($apiMessage ?: 'El asistente no pudo procesar la pregunta.');
        }

        $text = collect($response->json('candidates.0.content.parts') ?? [])
            ->map(fn ($part) => $part['text'] ?? '')
            ->implode('');

        $text = trim($text);

        if ($text === '') {
            throw new \RuntimeException('El asistente no devolvió una respuesta.');
        }

        return $text;
    }
}