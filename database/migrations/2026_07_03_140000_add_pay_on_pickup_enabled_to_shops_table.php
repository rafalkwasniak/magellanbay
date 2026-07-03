<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Płatność przy odbiorze (gotówką/na miejscu). Metoda płatności zależna od
 * dostawy: ma sens tylko przy włączonym odbiorze osobistym. Konfigurowana
 * osobno — nie każdy sprzedawca chce przyjmować płatność na miejscu. Bramka
 * `Shop::payOnPickupAvailable()` = fiszka ∧ odbiór realnie dostępny.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('pay_on_pickup_enabled')->default(true)->after('pickup_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('pay_on_pickup_enabled');
        });
    }
};
