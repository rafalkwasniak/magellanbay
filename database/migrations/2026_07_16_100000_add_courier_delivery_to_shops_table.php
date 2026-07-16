<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dostawa kurierem (Poziom 1 — bez integracji, bez mapy). Konfiguracja per sklep
 * obok odbioru osobistego: włącznik + koszt + opcjonalny próg darmowej dostawy.
 * `courier_free_from` NULL = brak progu (darmowej dostawy nie ma). Etykiety
 * przewoźnika (Poziom 2) dojdą osobno w `shop_integrations`, nie tutaj.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('courier_enabled')->default(false)->after('pay_on_pickup_enabled');
            $table->decimal('courier_cost', 10, 2)->nullable()->after('courier_enabled');
            $table->decimal('courier_free_from', 10, 2)->nullable()->after('courier_cost');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['courier_enabled', 'courier_cost', 'courier_free_from']);
        });
    }
};
