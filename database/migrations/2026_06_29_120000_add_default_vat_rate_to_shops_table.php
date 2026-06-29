<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domyślna stawka VAT sklepu — typowane pole na `shops` (kategoria „profil/
 * ustawienia", nie key/value). Formularz nowego produktu prefilluje się tą
 * wartością. Trzymane jako string spójny z enum App\Enums\VatRate (23/8/5/0/zw);
 * default '23' = najczęstsza stawka, więc istniejące sklepy dostają sensowną wartość.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('default_vat_rate', 3)->default('23')->after('apartment_number');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('default_vat_rate');
        });
    }
};
