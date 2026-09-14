<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class PushService
{
    public function isConfigured(): bool
    {
        return (bool) config('firebase.projects.app.credentials');
    }

    /**
     * Envía push a todos los dispositivos activos del usuario.
     * Los tokens que fallen se desactivan automáticamente.
     */
    public function sendToUser(int $userId, string $title, string $description, array $data = [], ?string $platform = null): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $tokens = \App\Models\DeviceToken::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->pluck('token', 'id');

        if ($tokens->isEmpty()) {
            return 0;
        }

        $messaging = app(Messaging::class);
        $sent = 0;

        foreach ($tokens as $tokenId => $token) {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(FcmNotification::create($title, $description))
                ->withData(array_merge($data, ['click_action' => 'OPEN_APP']));

            try {
                $messaging->send($message);
                $sent++;
            } catch (MessagingException $e) {
                Log::warning('[Push] Token fallido, desactivando', ['token_id' => $tokenId]);
                \App\Models\DeviceToken::where('id', $tokenId)->update(['is_active' => false]);
            }
        }

        return $sent;
    }

    /**
     * Envía push a una lista arbitraria de tokens FCM.
     * Los tokens que fallen se desactivan en la tabla indicada.
     */
    public function sendToTokens(array $tokens, string $title, string $description, array $data = []): int
    {
        if (! $this->isConfigured() || empty($tokens)) {
            return 0;
        }

        $messaging = app(Messaging::class);
        $sent = 0;

        foreach ($tokens as $token) {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(FcmNotification::create($title, $description))
                ->withData(array_merge($data, ['click_action' => 'OPEN_APP']));

            try {
                $messaging->send($message);
                $sent++;
            } catch (MessagingException $e) {
                Log::warning('[Push] Token fallido (lista externa)', ['token' => substr($token, 0, 12).'...']);
                \App\Models\TouristDeviceToken::where('token', $token)->update(['is_active' => false]);
            }
        }

        return $sent;
    }

    public function createNotification(int $userId, string $title, string $description, string $type = 'info'): void
    {
        \App\Models\AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'read' => false,
            'created_at' => now(),
        ]);
    }
}
