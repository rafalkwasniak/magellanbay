<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deduplikacja tagów po nazwie kanonicznej (z polskimi znakami), nie po
 * ASCII-slugu — żeby `ślub` nie zlewało się ze `slub` i zachowało ładny zapis.
 * Slug zostaje (pomocniczo, pod przyszłe adresy), ale nie jest kluczem unikalności.
 *
 * Kolejność ma znaczenie: indeks (shop_id,slug) podpiera FK `shop_id`, więc
 * najpierw dodajemy nowy unikalny (shop_id,name) (też pokrywa shop_id), a dopiero
 * potem usuwamy stary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unique(['shop_id', 'name']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unique(['shop_id', 'slug']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'name']);
        });
    }
};
