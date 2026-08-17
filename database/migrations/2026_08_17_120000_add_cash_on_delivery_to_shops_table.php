<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pobraniowe warianty obu wysyłek InPost. Bliźniaki kuriera i paczkomatu:
 * włącznik + koszt + opcjonalny próg darmowej dostawy (`free_from` NULL = brak
 * progu). Cztery metody dostawy włączają się NIEZALEŻNIE od siebie — sprzedawca
 * może chcieć np. sam paczkomat i sam paczkomat pobraniowy (decyzja Rafała
 * 17.08), więc pobranie nie dziedziczy stanu po metodzie „zwykłej".
 *
 * Cena pobrania jest WŁASNA, a nie dopłatą do metody bazowej: InPost liczy za
 * obsługę pobrania osobno, a sprzedawca ma sam zdecydować, ile z tego przerzuca
 * na klienta. Osobna kolumna trzyma tę decyzję wprost, bez liczenia „koszt
 * bazowy + narzut" w dwóch miejscach.
 *
 * Uprawnienia NIE dokładamy: wysyłkę odblokowuje `courier_shipping` (Stragan+),
 * a pobranie jest jej wariantem, nie osobną funkcją. Realnym warunkiem po
 * stronie sprzedawcy jest skonfigurowany InPost — to on inkasuje pieniądze —
 * i tego pilnuje model, nie schemat bazy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('courier_cod_enabled')->default(false)->after('parcel_locker_free_from');
            $table->decimal('courier_cod_cost', 10, 2)->nullable()->after('courier_cod_enabled');
            $table->decimal('courier_cod_free_from', 10, 2)->nullable()->after('courier_cod_cost');

            $table->boolean('parcel_locker_cod_enabled')->default(false)->after('courier_cod_free_from');
            $table->decimal('parcel_locker_cod_cost', 10, 2)->nullable()->after('parcel_locker_cod_enabled');
            $table->decimal('parcel_locker_cod_free_from', 10, 2)->nullable()->after('parcel_locker_cod_cost');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'courier_cod_enabled', 'courier_cod_cost', 'courier_cod_free_from',
                'parcel_locker_cod_enabled', 'parcel_locker_cod_cost', 'parcel_locker_cod_free_from',
            ]);
        });
    }
};
