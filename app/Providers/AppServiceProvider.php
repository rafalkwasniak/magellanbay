<?php

namespace App\Providers;

use App\Services\SlowQueryLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
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
