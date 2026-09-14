<?php

namespace App\Jobs;

use App\Services\PushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Encola la creación de una notificación in-app + envío push FCM al usuario.
 * Síncrono con QUEUE_CONNECTION=sync; asíncrono con database/redis + worker.
 */
class SendUserNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $title,
        public string $description,
        public string $type = 'info',
        public array $data = [],
    ) {
    }

    public function handle(PushService $push): void
    {
        $push->createNotification($this->userId, $this->title, $this->description, $this->type);
        $push->sendToUser($this->userId, $this->title, $this->description, $this->data);
    }
}
