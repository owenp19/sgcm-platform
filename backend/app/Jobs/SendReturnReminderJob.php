<?php

namespace App\Jobs;

use App\Models\TouristReturnReminder;
use App\Services\PushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envío individual de un recordatorio de retorno (email/push).
 * Se encola por turista: permite procesar millares sin bloquear el
 * scheduler y distribuye la carga en workers (database/redis).
 */
class SendReturnReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reminderId)
    {
    }

    public function handle(PushService $push): void
    {
        $reminder = TouristReturnReminder::with('card')->find($this->reminderId);

        if (! $reminder || $reminder->sent) {
            return;
        }

        $card = $reminder->card;

        if (! $card) {
            $reminder->update(['sent' => true, 'sent_at' => now()]);

            return;
        }

        try {
            match ($reminder->reminder_type) {
                'email' => $this->sendEmail($card),
                'push' => $this->sendPush($card, $push),
                'sms' => null, // Requiere proveedor SMS externo; se omite
            };

            $reminder->update(['sent' => true, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error("[Reminders] Error enviando recordatorio #{$this->reminderId}: {$e->getMessage()}");
        }
    }

    private function sendEmail($card): void
    {
        if (! $card->email) {
            return;
        }

        Mail::raw(
            "Hola {$card->first_name},\n\n".
            "Te recordamos que tu tarjeta de turismo SGCM (código {$card->code}) vence el ".
            $card->return_date?->format('d/m/Y').".\n\n".
            "Por favor programa tu retorno o renueva tu tarjeta si es necesario.\n\n".
            "SGCM San Andrés Islas",
            function ($message) use ($card) {
                $message->to($card->email)
                    ->subject('Recordatorio: tu tarjeta de turismo SGCM está por vencer');
            }
        );
    }

    private function sendPush($card, PushService $push): void
    {
        $tokens = \App\Models\TouristDeviceToken::query()
            ->where('card_id', $card->id)
            ->where('is_active', true)
            ->pluck('token')
            ->all();

        if (empty($tokens)) {
            return;
        }

        $push->sendToTokens($tokens, 'Tu tarjeta de turismo está por vencer',
            "La tarjeta {$card->code} vence el ".$card->return_date?->format('d/m/Y'));
    }
}
