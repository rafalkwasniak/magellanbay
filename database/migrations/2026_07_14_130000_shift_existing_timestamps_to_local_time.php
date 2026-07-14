<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migracja DANYCH, jednorazowa korekta historii. Do 14.07.2026 aplikacja chodziła
 * na `APP timezone = UTC`, mimo że PHP i MySQL na hostingu są na Europe/Warsaw.
 * Każdy stempel zapisany przez Laravela (`now()`) niósł więc godzinę UTC, którą
 * potem pokazywaliśmy jako lokalną — daty w mailach i na osi czasu zamówień były
 * młodsze o 2h. Po przestawieniu `config('app.timezone')` na Europe/Warsaw nowe
 * zapisy są poprawne, a te już istniejące trzeba przesunąć.
 *
 * Dlaczego równe +2h, bez rozróżniania wierszy: cała historia bazy zaczyna się
 * 25.06.2026, a więc mieści się w całości w czasie letnim (CEST = UTC+2). Żaden
 * wiersz nie przekracza granicy zmiany czasu, więc przesunięcie jest jednoznaczne.
 * Gdyby migracja ruszyła na pustej bazie (testy, nowa instalacja) — nie zrobi nic.
 *
 * Kolumny wypisane wprost, nie zbierane z information_schema: to ma być korekta
 * konkretnej, znanej historii, a nie automat przesuwający cokolwiek, co kiedyś
 * dojdzie do schematu. Pominięte tabele techniczne (`failed_jobs`,
 * `password_reset_tokens`) — ich stemple nie są niczym, na co ktoś patrzy, a
 * tokenom resetu i tak lepiej wygasnąć.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $columns = [
        'customers' => ['email_verified_at', 'created_at', 'updated_at'],
        'email_messages' => ['scheduled_at', 'sent_at', 'failed_at', 'created_at', 'updated_at'],
        'legal_documents' => ['published_at', 'created_at', 'updated_at'],
        'orders' => ['created_at', 'updated_at', 'deleted_at'],
        'order_items' => ['created_at', 'updated_at'],
        'order_status_events' => ['created_at'],
        'pages' => ['created_at', 'updated_at'],
        'products' => ['deleted_at', 'created_at', 'updated_at'],
        'product_images' => ['created_at', 'updated_at'],
        'product_price_history' => ['recorded_at'],
        'shops' => ['subscription_ends_at', 'created_at', 'updated_at'],
        'shop_integrations' => ['created_at', 'updated_at'],
        'tags' => ['created_at', 'updated_at'],
        'users' => ['email_verified_at', 'created_at', 'updated_at'],
        'user_consents' => ['accepted_at', 'created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->shift(2);
    }

    public function down(): void
    {
        $this->shift(-2);
    }

    private function shift(int $hours): void
    {
        // Korekta dotyczy wyłącznie historii produkcyjnej na MySQL. Na SQLite
        // (testy, świeża instalacja) nie ma czego przesuwać, a `DATE_ADD` i tak
        // jest MySQL-owe — bez tej bramki migracja wywracałaby testy.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)
                    ->whereNotNull($column)
                    ->update([$column => DB::raw("DATE_ADD(`{$column}`, INTERVAL {$hours} HOUR)")]);
            }
        }
    }
};
