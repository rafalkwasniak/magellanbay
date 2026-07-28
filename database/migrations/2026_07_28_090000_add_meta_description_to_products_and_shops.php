<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opis SEO (meta description) sklepu i produktu — to zdanie, które Google
 * pokazuje pod tytułem w wynikach wyszukiwania. Audyt ursalogic (16.07.2026):
 * 100% podstron bez opisu, przez co Google wycinał losowy fragment strony.
 *
 * Zapisany w bazie, nie liczony w locie: docelowo pisze go AI (osobny krok), a
 * wywołanie modelu przy każdym wejściu Googlebota byłoby absurdem kosztowym.
 *
 * `meta_description_manual` rozstrzyga SPÓR O WŁASNOŚĆ tekstu: gdy sprzedawca
 * napisze opis sam, automat nigdy go nie nadpisze. Wyczyszczenie pola oddaje
 * kontrolę z powrotem automatowi — to najprostszy możliwy sposób „cofnięcia",
 * bez dodatkowego przycisku.
 *
 * 255 znaków, choć w tagu wysyłamy ~155: zapas na dłuższy roboczy zapis
 * sprzedawcy, który i tak przytniemy przy renderowaniu.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'shops'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('meta_description', 255)->nullable()->after('description');
                $blueprint->boolean('meta_description_manual')->default(false)->after('meta_description');
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'shops'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['meta_description', 'meta_description_manual']);
            });
        }
    }
};
