<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Encola un correo (HTML o texto plano). Evita bloquear el request HTTP
 * durante el envío SMTP (latencia alta, fallos externos).
 */
class SendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public bool $raw = false,
    ) {
    }

    public function handle(): void
    {
        if ($this->raw) {
            Mail::raw($this->body, fn ($message) => $message->to($this->to)->subject($this->subject));

            return;
        }

        Mail::html($this->body, fn ($message) => $message->to($this->to)->subject($this->subject));
    }
}
