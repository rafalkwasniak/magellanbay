<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domyślne ustawienia NADAWANIA przesyłek per sklep (wcięty blok pod kartą
 * „Nadawanie przesyłek InPost” w Integracjach).
 *
 * `shipment_sending_method` — jak sprzedawca zwykle oddaje paczki. Domyślnie
 * darmowy paczkomat: odbiór kuriera jest dodatkowo płatny, więc nie może stać
 * się domyślnym przez przeoczenie. Deklaracja jest wiążąca w chwili nadania
 * ({@see \App\Enums\SendingMethod}), stąd ustawienie, a nie pytanie po fakcie.
 *
 * `courier_parcel_*` — domyślna paczka kurierska. Paczkomat opisuje się
 * gabarytem (szablon skrytki A/B/C), ale kurier chce WYMIARÓW I WAGI, a te są
 * u rękodzielnika zwykle powtarzalne; bez domyślnych wartości sprzedawca
 * wpisywałby to samo przy każdym zamówieniu. Tak samo robi panel InPostu.
 *
 * JEDNOSTKI W NAZWACH KOLUMN CELOWO: ShipX przyjmuje wyłącznie milimetry i
 * kilogramy, a interfejs pokazuje centymetry. Przeliczenie ma dziać się w
 * JEDNYM miejscu — przy budowaniu żądania do API — i nazwa kolumny ma nie
 * pozwolić o tym zapomnieć.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('shipment_sending_method', 20)->default('parcel_locker')->after('parcel_locker_free_from');
            $table->unsignedSmallInteger('courier_parcel_length_cm')->nullable()->after('shipment_sending_method');
            $table->unsignedSmallInteger('courier_parcel_width_cm')->nullable()->after('courier_parcel_length_cm');
            $table->unsignedSmallInteger('courier_parcel_height_cm')->nullable()->after('courier_parcel_width_cm');
            $table->decimal('courier_parcel_weight_kg', 5, 2)->nullable()->after('courier_parcel_height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'shipment_sending_method',
                'courier_parcel_length_cm',
                'courier_parcel_width_cm',
                'courier_parcel_height_cm',
                'courier_parcel_weight_kg',
            ]);
        });
    }
};
