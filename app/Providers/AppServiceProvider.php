<?php

namespace App\Providers;

use App\Listeners\RecordLogin;
use App\Listeners\ReportLockout;
use App\Services\AiQuota;
use App\Services\SlowQueryLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Jedna instancja na żądanie: `AiQuota` pamięta odczytane zużycie, a
        // licznik użyć AI renderuje się przy każdym przycisku (formularz produktu
        // ma ich dwa). Bez singletona każdy `app()` tworzyłby nowy obiekt z pustą
        // pamięcią i to samo zapytanie szłoby do bazy kilka razy.
        $this->app->singleton(AiQuota::class);
    }

    public function boot(): void
    {
        // Twardy blok destrukcyjnych komend (migrate:fresh/refresh/reset/rollback,
        // db:wipe) na produkcji — nie do obejścia flagą --force ani potwierdzeniem.
        // W środowisku testowym (APP_ENV=testing) wyłączony, więc RefreshDatabase
        // na sqlite :memory: działa normalnie. Patrz DB_SECURITY.md, Warstwa 1.
        DB::prohibitDestructiveCommands($this->app->environment('production'));

        $this->prohibitFactoriesInProduction();

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
     * Fabryki modeli zablokowane na produkcji (DB_SECURITY.md, Warstwa 7).
     *
     * Fabryka istnieje po to, żeby wypełnić bazę zmyślonymi danymi — na
     * produkcji nie ma to ŻADNEGO legalnego zastosowania. Blokada nic więc nie
     * odbiera, a zamyka całą klasę wpadek: `User::factory()->create()` wklepane
     * w tinkera „tylko na chwilę" zostawia w bazie konto z rolą sprzedawcy,
     * losowym nazwiskiem i domyślnym hasłem `password`.
     *
     * Lekcja z 2026-08-16: takie konto przeżyło w bazie 8 dni, aż konsola admina
     * pokazała je jako „sprzedawca bez sklepu". Testy tego nie zrobiły (chodzą
     * na sqlite `:memory:`) ani seeder (ten tworzy sztywny adres) — było to
     * ręczne wywołanie na produkcyjnym połączeniu.
     *
     * Wpięte przez resolver nazw fabryk, bo tamtędy przechodzi każde
     * `Model::factory()` (żaden nasz model nie ma własnego `newFactory()`).
     */
    private function prohibitFactoriesInProduction(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        Factory::guessFactoryNamesUsing(function (string $model): string {
            throw new RuntimeException(
                'Fabryki modeli są zablokowane na produkcji (próba: '.class_basename($model).'). '
                .'Fabryka zapisuje zmyślone dane — na żywej bazie nie ma zastosowania. '
                .'Patrz DB_SECURITY.md, Warstwa 7.'
            );
        });
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
