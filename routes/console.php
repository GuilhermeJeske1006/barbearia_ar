<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('agendamentos:enviar-lembretes')->everyFifteenMinutes();
Schedule::command('agendamentos:expirar-pendentes')->everyFifteenMinutes();
Schedule::command('despesas:gerar-recorrentes')->dailyAt('03:00');
