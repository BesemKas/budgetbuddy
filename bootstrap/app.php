<?php

use App\Http\Middleware\EnsureCurrentBudget;
use App\Http\Middleware\ShareSmartModeWithViews;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('budget:snapshot-periods')->monthlyOn(1, '1:00');
        $schedule->command('budget:apply-sinking-fund-rules')->monthlyOn(1, '2:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/dashboard');
        $middleware->web(append: [
            ShareSmartModeWithViews::class,
        ]);
        $middleware->alias([
            'budget' => EnsureCurrentBudget::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
