<?php

namespace App\Providers;

use App\Listeners\RecordLogin;
use App\Listeners\ReportLockout;
use App\Services\SlowQueryLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Jedna instancja na żądanie: `AiQuota` pamięta odczytane zużycie, a
        // licznik użyć AI renderuje się przy każdym przycisku (formularz produktu
        // ma ich dwa). Bez singletona każdy `app()` tworzyłby nowy obiekt z pustą
        // pamięcią i to samo zapytanie szłoby do bazy kilka razy.
        $this->app->singleton(\App\Services\AiQuota::class);
    }

    public function boot(): void
    {
        // Twardy blok destrukcyjnych komend (migrate:fresh/refresh/reset/rollback,
        // db:wipe) na produkcji — nie do obejścia flagą --force ani potwierdzeniem.
        // W środowisku testowym (APP_ENV=testing) wyłączony, więc RefreshDatabase
        // na sqlite :memory: działa normalnie. Patrz DB_SECURITY.md, Warstwa 1.
        DB::prohibitDestructiveCommands($this->app->environment('production'));

        if ((int) config('monitoring.slow_query_ms') > 0) {
            DB::listen(fn (QueryExecuted $query) => app(SlowQueryLogger::class)->handle($query));
        }

        $this->registerRateLimiters();

        // Wyczerpany limit prób logowania leci na kanał alertów — inaczej nie
        // wiemy, czy cisza oznacza spokój, czy tylko brak obserwacji.
        Event::listen(Lockout::class, ReportLockout::class);

        // Ostatnie logowanie na koncie — konsola admina odróżnia dzięki temu
        // sprzedawcę pracującego od takiego, który zarejestrował się i zniknął.
        Event::listen(Login::class, RecordLogin::class);
    }

    /**
     * Limity formularzy publicznych. Progi trzymamy w `config/security.php`, a
     * nie w łańcuchu `throttle:5,1` przy trasie — inaczej strojenie ochrony
     * oznacza szukanie liczb rozsypanych po pliku tras.
     */
    private function registerRateLimiters(): void
    {
        foreach (array_keys((array) config('security.public_forms')) as $name) {
            RateLimiter::for($name, function (Request $request) use ($name) {
                $limit = config('security.public_forms.'.$name);

                return Limit::perMinutes(
                    (int) $limit['decay_minutes'],
                    (int) $limit['max_attempts'],
                )->by($request->ip());
            });
        }
    }
}
