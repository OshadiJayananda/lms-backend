<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('books:mark-overdue')
    // ->dailyAt('00:00')
    ->everySecond()
    ->onFailure(function () {});

Schedule::command('reminders:send')->dailyAt('08:00');
