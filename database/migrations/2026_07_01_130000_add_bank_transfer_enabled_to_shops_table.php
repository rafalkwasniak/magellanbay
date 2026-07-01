<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiszka „Przelew na konto" — czy metoda „wpłata na konto" ma być widoczna w
 * kasie. Osobno od samego numeru konta (dana w profilu „Mój sklep"): numer to
 * fakt, fiszka to decyzja o pokazaniu metody. Metoda jest realnie dostępna tylko
 * gdy fiszka włączona ORAZ numer konta wypełniony (Shop::bankTransferAvailable()).
 * Default true — kto podał numer, ten domyślnie chce dostawać przelewy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('bank_transfer_enabled')->default(true)->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('bank_transfer_enabled');
        });
    }
};
