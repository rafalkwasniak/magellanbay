<?php

namespace App\Providers;

use App\Services\SlowQueryLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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
    }
}
