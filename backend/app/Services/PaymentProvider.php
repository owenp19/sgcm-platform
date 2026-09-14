<?php

namespace App\Services;

class PaymentProvider
{
    public const ALLOWED_PROVIDERS = ['sandbox'];

    public const ALLOWED_STATUSES = ['approved', 'completed', 'rejected', 'failed', 'cancelled', 'refunded'];

    public function environment(): string
    {
        return strtolower((string) config('services.payments.environment', 'sandbox'));
    }

    public function isSandbox(): bool
    {
        return $this->environment() !== 'production';
    }

    public function providerName(): string
    {
        $provider = strtolower((string) config('services.payments.provider', 'sandbox'));

        return in_array($provider, self::ALLOWED_PROVIDERS, true) ? $provider : 'sandbox';
    }

    public function webhookSecret(): string
    {
        return (string) config('services.payments.webhook_secret', 'sandbox-webhook-secret-change-me');
    }

    public function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->webhookSecret());
    }

    /**
     * Verificación de firma HMAC-SHA256 del webhook (mejora de seguridad:
     * en el backend Node existía pero no se aplicaba).
     */
    public function verifySignature(?string $rawBody, ?string $signature): bool
    {
        if (! $signature || ! $rawBody) {
            return false;
        }

        return hash_equals($this->signPayload($rawBody), (string) $signature);
    }

    public function isValidStatus(?string $status): bool
    {
        return in_array($status, self::ALLOWED_STATUSES, true);
    }

    public function isApprovedStatus(string $status): bool
    {
        return in_array($status, ['approved', 'completed'], true);
    }

    public function buildPaymentUrl(string $code, string $reference): ?string
    {
        if ($this->isSandbox()) {
            $frontend = rtrim((string) config('services.frontend_url', 'http://localhost:8100'), '/');

            return $frontend.'/tarjeta-turismo/sandbox/'.$code.'?reference='.urlencode($reference);
        }

        $gateway = config('services.payments.gateway_url');

        return $gateway ? rtrim($gateway, '/').'/checkout?reference='.urlencode($reference) : null;
    }
}
