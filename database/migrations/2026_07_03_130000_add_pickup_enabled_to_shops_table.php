<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odbiór osobisty (spec „Dostawy → Odbiór osobisty"): przełącznik na poziomie
 * sklepu, adres odbioru bierze się z danych sklepu. Wzorzec bliźniaczy do
 * `bank_transfer_enabled` — default true, ale realnie działa dopiero, gdy adres
 * sklepu jest kompletny (bramka `Shop::pickupAvailable()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('pickup_enabled')->default(true)->after('bank_transfer_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('pickup_enabled');
        });
    }
};
