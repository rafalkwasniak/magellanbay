<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Znacznik produktu ukrytego PRZEZ SYSTEM po wygaśnięciu abonamentu (miękki
 * zamek limitu).
 *
 * Bez tej kolumny „po opłacie wracają jednym ruchem" jest niewykonalne: nie da
 * się odróżnić produktu schowanego przez zamek od tego, który sprzedawca ukrył
 * sam (koniec sezonu, brak towaru) — przywracanie byłoby zgadywaniem i cofałoby
 * jego własne decyzje.
 *
 * Ustawiany przy zamknięciu, czyszczony przy przywróceniu. Ręczne ukrycie
 * (`is_active = false` bez tego znacznika) system traktuje jako nietykalne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('auto_hidden_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('auto_hidden_at');
        });
    }
};
