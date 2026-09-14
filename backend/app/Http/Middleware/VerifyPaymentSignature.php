<?php

namespace App\Http\Middleware;

use App\Services\PaymentProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentSignature
{
    public function __construct(private readonly PaymentProvider $payments)
    {
    }

    /**
     * Verifica la firma HMAC-SHA256 del webhook de pagos.
     * Header esperado: X-Payment-Signature: <hex>
     * Se puede desactivar en desarrollo con PAYMENT_WEBHOOK_VERIFY=false.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (env('PAYMENT_WEBHOOK_VERIFY', true) === false) {
            return $next($request);
        }

        $signature = $request->header('X-Payment-Signature');

        if (! $this->payments->verifySignature($request->getContent(), $signature)) {
            return response()->json(['error' => 'Firma de webhook inválida'], 401);
        }

        return $next($request);
    }
}
