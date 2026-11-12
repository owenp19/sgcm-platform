<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourismTariff;
use App\Models\TouristCard;
use App\Models\TouristCardPayment;
use App\Models\TouristCardReceipt;
use App\Models\TouristLocationEvent;
use App\Models\TouristReturnReminder;
use App\Services\PaymentProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class TourismCardController extends Controller
{
    public function __construct(private readonly PaymentProvider $payments)
    {
    }

    private function activeTariff(): ?TourismTariff
    {
        // La tarifa activa se consulta en cada petición pública (tariff/card/quote).
        // Se cachea 5 minutos para soportar picos de concurrencia sin golpear MySQL.
        return Cache::remember('tourism.active_tariff', 300, function () {
            return TourismTariff::query()
                ->where('active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderByDesc('id')
                ->first();
        });
    }

    private function generateCode(): string
    {
        $year = now()->year;
        $attempts = 0;

        do {
            // 12 caracteres aleatorios (≈62^12) en lugar de 6 dígitos:
            // el código del recibo es un secreto de acceso a datos personales.
            $code = sprintf('SGCM-TT-%d-%s', $year, Str::upper(Str::random(12)));
            $exists = TouristCard::where('code', $code)->exists();
            if (++$attempts > 100) {
                throw new \RuntimeException('No se pudo generar un código único después de 100 intentos');
            }
        } while ($exists);

        return $code;
    }

    /**
     * Los códigos antiguos (6 dígitos u otros formatos) son enumerables y no
     * deben servir por sí solos para divulgar datos personales.
     */
    private function isHighEntropyCode(string $code): bool
    {
        return (bool) preg_match('/^SGCM-TT-\d{4}-[A-Z0-9]{12}$/', $code);
    }

    /**
     * Credencial firmada de acceso al recibo. Se entrega al crear la tarjeta y
     * permite consultar el recibo sin depender de la entropía del código.
     */
    private function receiptAccessToken(TouristCard $card): string
    {
        $payload = $card->id.'.'.$card->code.'.'.now()->addDays(30)->timestamp;
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));

        return rtrim(strtr(base64_encode($payload.'.'.$signature), '+/', '-_'), '=');
    }

    private function hasValidReceiptToken(?string $token, TouristCard $card): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode('.', $decoded);
        if (count($parts) !== 4) {
            return false;
        }

        [$id, $code, $expiresAt, $signature] = $parts;
        $payload = $id.'.'.$code.'.'.$expiresAt;
        $expected = hash_hmac('sha256', $payload, (string) config('app.key'));

        return hash_equals($expected, $signature)
            && (int) $expiresAt >= now()->timestamp
            && (int) $id === (int) $card->id
            && $code === $card->code;
    }

    private function requestingStaff(Request $request): bool
    {
        if (! $request->bearerToken()) {
            return false;
        }

        try {
            $user = auth('api')->user();
        } catch (\Throwable) {
            return false;
        }

        return $user !== null && $user->hasRole('admin', 'funcionario');
    }

    private function maskName(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return mb_substr($value, 0, 1).str_repeat('*', max(2, mb_strlen($value) - 1));
    }

    private function maskEmail(?string $value): string
    {
        if ($value === null || $value === '' || ! str_contains($value, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $value, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function maskTail(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return str_repeat('*', max(2, mb_strlen($value) - 2)).mb_substr($value, -2);
    }

    #[OA\Get(
        path: '/tourism-card/tariff',
        summary: 'Obtener la tarifa activa de la tarjeta de turismo',
        tags: ['TourismCard'],
        responses: [
            new OA\Response(response: 200, description: 'Tarifa activa',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'concept', type: 'string'),
                        new OA\Property(property: 'amount', type: 'number'),
                        new OA\Property(property: 'currency', type: 'string'),
                        new OA\Property(property: 'active', type: 'boolean'),
                    ]
                )),
            new OA\Response(response: 404, description: 'No hay tarifa activa'),
        ]
    )]
    public function getTariff(): JsonResponse
    {
        $tariff = $this->activeTariff();

        if (! $tariff) {
            return response()->json(['error' => 'No hay tarifa activa'], 404);
        }

        return response()->json([
            'concept' => $tariff->concept,
            'amount' => $tariff->amount,
            'currency' => $tariff->currency,
            'active' => (bool) $tariff->active,
        ]);
    }

    #[OA\Post(
        path: '/tourism-card/quote',
        summary: 'Cotizar el valor de la tarjeta de turismo',
        tags: ['TourismCard'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['birth_date', 'entry_date', 'return_date'],
                properties: [
                    new OA\Property(property: 'birth_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'entry_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'return_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'document_type', type: 'string'),
                    new OA\Property(property: 'document_number', type: 'string'),
                    new OA\Property(property: 'nationality', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cotización calculada'),
            new OA\Response(response: 404, description: 'No hay tarifa activa'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'birth_date' => ['required'],
            'entry_date' => ['required'],
            'return_date' => ['required'],
            'document_type' => ['nullable'],
            'document_number' => ['nullable'],
            'nationality' => ['nullable'],
        ]);

        $tariff = $this->activeTariff();

        if (! $tariff) {
            return response()->json(['error' => 'No hay tarifa activa'], 404);
        }

        return response()->json([
            'requires_payment' => true,
            'amount' => $tariff->amount,
            'currency' => $tariff->currency,
            'concept' => $tariff->concept,
            'exemption_reason' => null,
        ]);
    }

    #[OA\Post(
        path: '/tourism-card/card',
        summary: 'Crear una solicitud de tarjeta de turismo',
        tags: ['TourismCard'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'document_type', 'document_number', 'email', 'phone', 'entry_date', 'return_date', 'accepted_terms'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'last_name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'document_type', type: 'string', maxLength: 50),
                    new OA\Property(property: 'document_number', type: 'string', maxLength: 50),
                    new OA\Property(property: 'birth_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'nationality', type: 'string', maxLength: 100),
                    new OA\Property(property: 'country_residence', type: 'string', maxLength: 100),
                    new OA\Property(property: 'city_residence', type: 'string', maxLength: 100),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 50),
                    new OA\Property(property: 'emergency_contact_name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'emergency_contact_phone', type: 'string', maxLength: 50),
                    new OA\Property(property: 'entry_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'return_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'transport_type', type: 'string', maxLength: 100),
                    new OA\Property(property: 'airline_or_company', type: 'string', maxLength: 255),
                    new OA\Property(property: 'flight_number', type: 'string', maxLength: 50),
                    new OA\Property(property: 'origin_city', type: 'string', maxLength: 100),
                    new OA\Property(property: 'travel_reason', type: 'string', maxLength: 255),
                    new OA\Property(property: 'lodging_type', type: 'string', maxLength: 100),
                    new OA\Property(property: 'lodging_name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'lodging_address', type: 'string', maxLength: 500),
                    new OA\Property(property: 'lodging_sector', type: 'string', maxLength: 100),
                    new OA\Property(property: 'lodging_phone', type: 'string', maxLength: 50),
                    new OA\Property(property: 'lodging_responsible_name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'accepted_terms', type: 'boolean', default: true),
                    new OA\Property(property: 'accepted_location_consent', type: 'boolean', default: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Solicitud de tarjeta creada'),
            new OA\Response(response: 400, description: 'Debe aceptar términos y condiciones'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:50'],
            'document_number' => ['required', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'country_residence' => ['nullable', 'string', 'max:100'],
            'city_residence' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'entry_date' => ['required', 'date'],
            'return_date' => ['required', 'date'],
            'transport_type' => ['nullable', 'string', 'max:100'],
            'airline_or_company' => ['nullable', 'string', 'max:255'],
            'flight_number' => ['nullable', 'string', 'max:50'],
            'origin_city' => ['nullable', 'string', 'max:100'],
            'travel_reason' => ['nullable', 'string', 'max:255'],
            'lodging_type' => ['nullable', 'string', 'max:100'],
            'lodging_name' => ['nullable', 'string', 'max:255'],
            'lodging_address' => ['nullable', 'string', 'max:500'],
            'lodging_sector' => ['nullable', 'string', 'max:100'],
            'lodging_phone' => ['nullable', 'string', 'max:50'],
            'lodging_responsible_name' => ['nullable', 'string', 'max:255'],
            'accepted_terms' => ['required', 'boolean'],
            'accepted_location_consent' => ['nullable', 'boolean'],
        ]);

        if (! $data['accepted_terms']) {
            return response()->json(['error' => 'Debe aceptar términos y condiciones'], 400);
        }

        $tariff = $this->activeTariff();
        $amount = $tariff?->amount ?? 0;
        $currency = $tariff?->currency ?? 'COP';

        $card = TouristCard::create([
            ...collect($data)->except(['accepted_terms', 'accepted_location_consent'])->all(),
            'code' => $this->generateCode(),
            'amount' => $amount,
            'currency' => $currency,
            'payment_status' => 'Pendiente',
            'card_status' => 'Pendiente de pago',
            'accepted_terms' => true,
            'accepted_location_consent' => (bool) ($data['accepted_location_consent'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $card->id,
                'code' => $card->code,
                'receipt_token' => $this->receiptAccessToken($card),
                'payment_status' => 'Pendiente',
                'card_status' => 'Pendiente de pago',
                'amount' => $amount,
                'currency' => $currency,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/tourism-card/card/{code}/payment/init',
        summary: 'Inicializar el pago de la tarjeta de turismo',
        tags: ['TourismCard'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'URL de pago generada'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
        ]
    )]
    public function initPayment(string $code): JsonResponse
    {
        $card = TouristCard::where('code', $code)->first();
        if (! $card) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        $reference = "PAY-{$code}";
        $paymentUrl = $this->payments->buildPaymentUrl($code, $reference);

        TouristCardPayment::create([
            'tourist_card_id' => $card->id,
            'payment_reference' => $reference,
            'gateway' => 'pasarela',
            'amount' => $card->amount,
            'currency' => $card->currency,
            'status' => 'Pendiente',
        ]);

        $card->update(['payment_reference' => $reference]);

        return response()->json([
            'success' => true,
            'payment_url' => $paymentUrl ?? rtrim((string) config('services.frontend_url', 'http://localhost:8100'), '/')."/tarjeta-turismo/pago/{$code}/sandbox",
            'reference' => $reference,
        ]);
    }

    #[OA\Get(
        path: '/tourism-card/card/{code}/payment/status',
        summary: 'Consultar el estado de pago de la tarjeta',
        tags: ['TourismCard'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado de pago actual'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
        ]
    )]
    public function checkPaymentStatus(string $code): JsonResponse
    {
        $card = TouristCard::where('code', $code)->first();
        if (! $card) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        return response()->json([
            'code' => $card->code,
            'payment_status' => $card->payment_status,
            'card_status' => $card->card_status,
            'amount' => $card->amount,
            'currency' => $card->currency,
        ]);
    }

    #[OA\Post(
        path: '/tourism-card/search',
        summary: 'Buscar una tarjeta por código y documento',
        tags: ['TourismCard'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'document_number'],
                properties: [
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'document_number', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tarjeta encontrada'),
            new OA\Response(response: 404, description: 'No se encontró registro con esos datos'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function searchCard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'document_number' => ['required', 'string'],
        ]);

        $card = TouristCard::where('code', $data['code'])
            ->where('document_number', $data['document_number'])
            ->first([
                'code', 'first_name', 'last_name', 'document_type', 'document_number',
                'nationality', 'entry_date', 'return_date', 'lodging_name', 'lodging_sector',
                'amount', 'currency', 'payment_status', 'card_status', 'qr_code', 'receipt_url',
                'created_at',
            ]);

        if (! $card) {
            return response()->json(['error' => 'No se encontró registro con esos datos'], 404);
        }

        return response()->json($card);
    }

    #[OA\Get(
        path: '/tourism-card/card/{code}/receipt',
        summary: 'Obtener el recibo de la tarjeta de turismo',
        tags: ['TourismCard'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Recibo de la tarjeta'),
            new OA\Response(response: 404, description: 'Recibo no encontrado'),
        ]
    )]
    public function getReceipt(Request $request, string $code): JsonResponse
    {
        $card = TouristCard::where('code', $code)
            ->with(['receipts' => fn ($q) => $q->latest('id')->limit(1)])
            ->first();

        if (! $card) {
            return response()->json(['error' => 'Recibo no encontrado'], 404);
        }

        $receipt = $card->receipts->first();

        // Acceso completo: staff autenticado, código de alta entropía o
        // credencial firmada entregada al crear la tarjeta.
        $fullAccess = $this->requestingStaff($request)
            || $this->isHighEntropyCode($card->code)
            || $this->hasValidReceiptToken($request->query('token'), $card);

        $data = [
            'code' => $card->code,
            'receipt_number' => $receipt?->receipt_number,
            'pdf_path' => $receipt?->pdf_path,
            'qr_payload' => $receipt?->qr_payload,
            'first_name' => $card->first_name,
            'last_name' => $card->last_name,
            'document_type' => $card->document_type,
            'document_number' => $card->document_number,
            'birth_date' => $card->birth_date?->toDateString(),
            'nationality' => $card->nationality,
            'country_residence' => $card->country_residence,
            'city_residence' => $card->city_residence,
            'email' => $card->email,
            'phone' => $card->phone,
            'emergency_contact_name' => $card->emergency_contact_name,
            'emergency_contact_phone' => $card->emergency_contact_phone,
            'entry_date' => $card->entry_date?->toDateString(),
            'return_date' => $card->return_date?->toDateString(),
            'transport_type' => $card->transport_type,
            'airline_or_company' => $card->airline_or_company,
            'flight_number' => $card->flight_number,
            'origin_city' => $card->origin_city,
            'travel_reason' => $card->travel_reason,
            'lodging_type' => $card->lodging_type,
            'lodging_name' => $card->lodging_name,
            'lodging_address' => $card->lodging_address,
            'lodging_sector' => $card->lodging_sector,
            'lodging_phone' => $card->lodging_phone,
            'lodging_responsible_name' => $card->lodging_responsible_name,
            'amount' => $card->amount,
            'currency' => $card->currency,
            'payment_status' => $card->payment_status,
            'card_status' => $card->card_status,
            'created_at' => $card->created_at?->toISOString(),
            'pii_masked' => ! $fullAccess,
        ];

        if (! $fullAccess) {
            // Nunca devolver el QR ni la ruta del PDF en modo restringido:
            // ambos pueden contener el token que da acceso a los datos.
            $data['pdf_path'] = null;
            $data['qr_payload'] = null;
            $data['first_name'] = $this->maskName($card->first_name);
            $data['last_name'] = $this->maskName($card->last_name);
            $data['document_number'] = $this->maskTail($card->document_number);
            $data['birth_date'] = null;
            $data['country_residence'] = $card->country_residence ? '***' : null;
            $data['city_residence'] = $card->city_residence ? '***' : null;
            $data['email'] = $this->maskEmail($card->email);
            $data['phone'] = $this->maskTail($card->phone);
            $data['emergency_contact_name'] = $this->maskName($card->emergency_contact_name);
            $data['emergency_contact_phone'] = $this->maskTail($card->emergency_contact_phone);
            $data['lodging_address'] = $card->lodging_address ? '***' : null;
            $data['lodging_phone'] = $this->maskTail($card->lodging_phone);
            $data['lodging_responsible_name'] = $this->maskName($card->lodging_responsible_name);
        }

        return response()->json($data);
    }

    #[OA\Get(
        path: '/tourism-card/card/{code}/verify',
        summary: 'Verificar la validez de una tarjeta de turismo',
        tags: ['TourismCard'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resultado de verificación'),
            new OA\Response(response: 404, description: 'Código no válido'),
        ]
    )]
    public function verifyCard(string $code): JsonResponse
    {
        $card = TouristCard::where('code', $code)->first();
        if (! $card) {
            return response()->json(['error' => 'Código no válido'], 404);
        }

        $docNumber = (string) $card->document_number;
        $hiddenDoc = strlen($docNumber) > 4
            ? str_repeat('*', strlen($docNumber) - 4).substr($docNumber, -4)
            : '****'.substr($docNumber, -2);

        $isValid = $card->payment_status === 'Pagado'
            && $card->return_date !== null
            && $card->return_date->startOfDay()->gte(now()->startOfDay());

        return response()->json([
            'code' => $card->code,
            'status' => $card->card_status,
            'payment_status' => $card->payment_status,
            'entry_date' => $card->entry_date?->toDateString(),
            'return_date' => $card->return_date?->toDateString(),
            'document' => "{$card->document_type} {$hiddenDoc}",
            'valid' => $isValid,
            'message' => $isValid ? 'Documento válido' : 'Documento vencido o no pagado',
        ]);
    }

    #[OA\Get(
        path: '/tourism-card/card/verify/qr/{qr_token}',
        summary: 'Verificar una tarjeta mediante token QR',
        tags: ['TourismCard'],
        parameters: [
            new OA\Parameter(name: 'qr_token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resultado de verificación'),
            new OA\Response(response: 404, description: 'Código QR no válido'),
        ]
    )]
    public function verifyByQrToken(string $qrToken): JsonResponse
    {
        $card = TouristCard::where('qr_token', $qrToken)->first();
        if (! $card) {
            return response()->json(['error' => 'Código QR no válido'], 404);
        }

        $isExpired = $card->expires_at !== null && $card->expires_at->lt(now());
        $isValid = $card->payment_status === 'Pagado' && ! $isExpired;

        $docNumber = (string) $card->document_number;
        $hiddenDoc = strlen($docNumber) > 4
            ? str_repeat('*', strlen($docNumber) - 4).substr($docNumber, -4)
            : '****'.substr($docNumber, -2);

        // No se exponen nombres completos ni documento sin máscara por validación QR pública.
        return response()->json([
            'code' => $card->code,
            'document' => "{$card->document_type} {$hiddenDoc}",
            'status' => $isExpired ? 'Vencida' : $card->card_status,
            'payment_status' => $card->payment_status,
            'entry_date' => $card->entry_date?->toDateString(),
            'return_date' => $card->return_date?->toDateString(),
            'valid' => $isValid,
            'message' => $isValid ? 'Documento válido' : 'Documento vencido o no pagado',
        ]);
    }

    #[OA\Post(
        path: '/tourism-card/card/{code}/share-location',
        summary: 'Registrar la ubicación del turista',
        tags: ['TourismCard'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'latitude', type: 'number'),
                    new OA\Property(property: 'longitude', type: 'number'),
                    new OA\Property(property: 'accuracy', type: 'number'),
                    new OA\Property(property: 'reason', type: 'string', maxLength: 100),
                    new OA\Property(property: 'captured_at', type: 'string', format: 'date-time'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ubicación registrada'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function shareLocation(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:100'],
            'captured_at' => ['nullable', 'date'],
        ]);

        $card = TouristCard::where('code', $code)->first();
        if (! $card) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        TouristLocationEvent::create([
            'tourist_card_id' => $card->id,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy' => $data['accuracy'] ?? null,
            'reason' => $data['reason'] ?? 'voluntario',
            'captured_at' => isset($data['captured_at']) ? \Carbon\Carbon::parse($data['captured_at']) : now(),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Ubicación registrada']);
    }

    private function generateReceiptNumber(): string
    {
        $year = now()->year;
        $count = TouristCardReceipt::whereYear('created_at', $year)->count();

        return sprintf('RC-%d-%06d', $year, $count + 1);
    }

    /**
     * Webhook de la pasarela de pagos. Protegido con firma HMAC-SHA256
     * (header X-Payment-Signature) vía middleware VerifyPaymentSignature.
     */
    #[OA\Post(
        path: '/tourism-card/payment/webhook',
        summary: 'Webhook de confirmación de pago de la pasarela',
        tags: ['TourismCard'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reference', 'status'],
                properties: [
                    new OA\Property(property: 'reference', type: 'string'),
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(property: 'gateway_response', type: 'object', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Webhook procesado'),
            new OA\Response(response: 400, description: 'Estado no permitido'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function paymentWebhook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string'],
            'status' => ['required', 'string'],
            'gateway_response' => ['nullable', 'array'],
        ]);

        if (! $this->payments->isValidStatus($data['status'])) {
            return response()->json(['error' => 'Estado no permitido'], 400);
        }

        $reference = $data['reference'];
        $code = Str::after($reference, 'PAY-');

        $card = TouristCard::where('code', $code)->first();
        if (! $card) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        $isApproved = $this->payments->isApprovedStatus($data['status']);
        $paymentStatus = $isApproved ? 'Pagado' : 'Rechazado';
        $cardStatus = $isApproved ? 'Emitida' : 'Pendiente de pago';

        DB::transaction(function () use ($card, $isApproved, $paymentStatus, $cardStatus, $reference, $data) {
            TouristCardPayment::where('payment_reference', $reference)->update([
                'status' => $paymentStatus,
                'gateway_response' => isset($data['gateway_response']) ? json_encode($data['gateway_response']) : null,
                'paid_at' => $isApproved ? now() : null,
            ]);

            if ($isApproved) {
                $receiptNumber = $this->generateReceiptNumber();
                $qrToken = Str::uuid()->toString();
                $frontendUrl = rtrim((string) config('services.frontend_url', 'http://localhost:8100'), '/');
                $qrValidationUrl = "{$frontendUrl}/validar-tarjeta-turismo/qr/{$qrToken}";
                $issuedAt = now();
                $expiresAt = $card->return_date?->copy()->setTime(23, 59, 59);

                $card->forceFill([
                    'payment_status' => 'Pagado',
                    'card_status' => 'Emitida',
                    'receipt_number' => $receiptNumber,
                    'payment_reference' => $reference,
                    'qr_token' => $qrToken,
                    'qr_validation_url' => $qrValidationUrl,
                    'issued_at' => $issuedAt,
                    'expires_at' => $expiresAt,
                ])->save();

                TouristCardReceipt::create([
                    'tourist_card_id' => $card->id,
                    'receipt_number' => $receiptNumber,
                    'qr_payload' => $qrValidationUrl,
                    'qr_token' => $qrToken,
                    'issued_at' => $issuedAt,
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                ]);

                // Recordatorios según preferencias del turista
                $reminderDate = $card->return_date
                    ? $card->return_date->copy()->subDays(max(0, (int) $card->reminder_days_before))
                    : null;

                foreach (['email', 'sms', 'push'] as $type) {
                    $flag = match ($type) {
                        'email' => $card->notify_email,
                        'sms' => $card->notify_sms,
                        'push' => $card->notify_push,
                    };

                    if ($flag && $reminderDate) {
                        TouristReturnReminder::create([
                            'tourist_card_id' => $card->id,
                            'reminder_type' => $type,
                            'remind_at' => $reminderDate,
                            'created_at' => now(),
                        ]);
                    }
                }
            } else {
                $card->forceFill([
                    'payment_status' => $paymentStatus,
                    'card_status' => $cardStatus,
                ])->save();
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Marca tarjetas vencidas. Ejecutado por el scheduler cada hora.
     */
    public static function updateCardsExpired(): int
    {
        $affected = TouristCard::query()
            ->where('payment_status', 'Pagado')
            ->where('card_status', '!=', 'Vencida')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['card_status' => 'Vencida']);

        if ($affected > 0) {
            Log::info("[TourismCard] {$affected} tarjeta(s) marcada(s) como vencida(s)");
        }

        return $affected;
    }

    /**
     * Acción HTTP para el endpoint de mantenimiento /expired/check.
     * Delega en el mismo proceso que ejecuta el scheduler. Devuelve el total
     * de tarjetas marcadas como vencidas en esta pasada.
     */
    #[OA\Post(
        path: '/tourism-card/expired/check',
        summary: 'Marcar como vencidas las tarjetas expiradas',
        tags: ['TourismCard'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Ejecución del proceso de vencimiento'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function updateCardStatusExpired(Request $request): JsonResponse
    {
        $affected = self::updateCardsExpired();

        return response()->json([
            'success' => true,
            'expired' => $affected,
            'run_at' => now()->toIso8601String(),
        ]);
    }
}
