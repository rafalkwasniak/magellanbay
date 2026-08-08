<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opis paczki faktycznie nadanej z tego zamówienia oraz zadeklarowany sposób
 * nadania. Migawka z chwili nadania, nie odczyt z bieżących ustawień sklepu —
 * sprzedawca może zmienić domyślne wartości nazajutrz, a etykieta i tak została
 * wydrukowana na te.
 *
 * `shipment_size` (gabaryt A/B/C) ZOSTAJE nietknięty i dalej opisuje przesyłki
 * paczkomatowe — to szablon skrytki, więc przy kurierze nie znaczy nic. Dwie
 * metody dostawy opisują paczkę inaczej i próba wciśnięcia ich w jedną kolumnę
 * skończyłaby się polem, które raz znaczy „A”, a raz „30×20×10”.
 *
 * `shipment_sending_method` trzymamy przy zamówieniu, bo jest WIĄŻĄCY po
 * stronie InPostu: bez niego nie da się później stwierdzić, czy tę paczkę może
 * zabrać kurier, czy sprzedawca musi ją zanieść ({@see \App\Enums\SendingMethod}).
 *
 * Jednostki w nazwach kolumn — patrz migracja domyślnych ustawień sklepu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipment_sending_method', 20)->nullable()->after('shipment_size');
            $table->unsignedSmallInteger('shipment_length_cm')->nullable()->after('shipment_sending_method');
            $table->unsignedSmallInteger('shipment_width_cm')->nullable()->after('shipment_length_cm');
            $table->unsignedSmallInteger('shipment_height_cm')->nullable()->after('shipment_width_cm');
            $table->decimal('shipment_weight_kg', 5, 2)->nullable()->after('shipment_height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipment_sending_method',
                'shipment_length_cm',
                'shipment_width_cm',
                'shipment_height_cm',
                'shipment_weight_kg',
            ]);
        });
    }
};
