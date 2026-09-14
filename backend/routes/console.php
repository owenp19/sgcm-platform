<?php

use App\Http\Controllers\Api\TourismCardController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Equivalente al cron de Node: marcar tarjetas vencidas cada hora
Schedule::call(function () {
    TourismCardController::updateCardsExpired();
})->hourly()->name('tourism-cards-expire');

// Enviar recordatorios de retorno a turistas (24h y 48h antes del return_date)
Schedule::call(function () {
    Artisan::call('tourism:send-return-reminders');
})->hourly()->name('tourism-return-reminders');
