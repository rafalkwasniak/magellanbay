<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dostawa do paczkomatu InPost (Poziom 1 — bez integracji sprzedawcy). Bliźniak
 * kuriera: włącznik + koszt + opcjonalny próg darmowej dostawy; `free_from`
 * NULL = brak progu. Mapa (geowidget) jest warstwą NA WIERZCHU tej konfiguracji
 * i nie warunkuje jej działania — klient może wpisać kod paczkomatu z palca.
 * Tokeny ShipX i etykiety (Poziom 2) dojdą w `shop_integrations`, nie tutaj.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('parcel_locker_enabled')->default(false)->after('courier_free_from');
            $table->decimal('parcel_locker_cost', 10, 2)->nullable()->after('parcel_locker_enabled');
            $table->decimal('parcel_locker_free_from', 10, 2)->nullable()->after('parcel_locker_cost');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['parcel_locker_enabled', 'parcel_locker_cost', 'parcel_locker_free_from']);
        });
    }
};
