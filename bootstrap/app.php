<?php

use App\Http\Middleware\AuthenticateCustomer;
use App\Http\Middleware\EnsureConsentsAreCurrent;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ResolveShop;
use App\Services\DiscordErrorReporter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'ensure.consents' => EnsureConsentsAreCurrent::class,
            'tenant' => ResolveShop::class,
            'auth.customer' => AuthenticateCustomer::class,
        ]);

        // Niezalogowani trafiają na ekran logowania.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Webhook Paynow przychodzi z zewnątrz, bez tokenu CSRF — broni go podpis
        // (weryfikowany w kontrolerze), nie sesja. To jedyny wyjątek.
        $middleware->validateCsrfTokens(except: [
            'platnosci/paynow/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Błędy w JSON dla żądań AJAX/JSON (np. „Popraw przez AI"); zwykłe
        // formularze webowe nadal dostają redirect z błędami w sesji.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Report unhandled exceptions to Discord, in addition to the log.
        $exceptions->report(function (Throwable $e): void {
            app(DiscordErrorReporter::class)->report($e);
        });
    })->create();
