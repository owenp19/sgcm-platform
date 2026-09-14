<?php

namespace App\Console\Commands;

use App\Jobs\SendReturnReminderJob;
use App\Models\TouristReturnReminder;
use Illuminate\Console\Command;

class SendReturnReminders extends Command
{
    protected $signature = 'tourism:send-return-reminders';

    protected $description = 'Encola recordatorios de retorno pendientes a turistas con tarjeta SGCM';

    public function handle(): int
    {
        $due = TouristReturnReminder::query()
            ->where('sent', false)
            ->where('remind_at', '<=', now())
            ->limit(200)
            ->get();

        if ($due->isEmpty()) {
            $this->info('Sin recordatorios pendientes.');

            return self::SUCCESS;
        }

        $queued = 0;

        foreach ($due as $reminder) {
            // Se encola un job por turista. El job marca 'sent' cuando se
            // procesa. Con QUEUE_CONNECTION=sync se envía al instante; con
            // database/redis se distribuye en workers para alta concurrencia.
            SendReturnReminderJob::dispatch($reminder->id);
            $queued++;
        }

        $this->info("{$queued} recordatorio(s) encolado(s).");

        return self::SUCCESS;
    }
}
