<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wskazany przez klienta paczkomat — migawka na zamówieniu, obok migawki adresu
 * (`ship_*`). Te dwa zestawy WYKLUCZAJĄ się: kurier ma adres i pusty paczkomat,
 * paczkomat odwrotnie (patrz DeliveryMethod::requiresShippingAddress()).
 *
 * `parcel_locker_code` to identyfikator InPostu (np. KRA01A) — to on jedzie na
 * etykietę i po nim sprzedawca nadaje paczkę. `parcel_locker_address` to
 * czytelny opis lokalizacji, zamrożony na moment zakupu: paczkomaty bywają
 * przenoszone i likwidowane, a zamówienie ma pamiętać, co klient wtedy wybrał
 * (ta sama zasada, co przy cenach i nazwach pozycji).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('parcel_locker_code', 20)->nullable()->after('ship_city');
            $table->string('parcel_locker_address')->nullable()->after('parcel_locker_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['parcel_locker_code', 'parcel_locker_address']);
        });
    }
};
