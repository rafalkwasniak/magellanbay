<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dane do przelewu tradycyjnego — typowane pola na `shops` (kategoria „profil/
 * ustawienia", nie key/value). Numer konta trzymamy znormalizowany do 26 cyfr
 * polskiego NRB (bez spacji i prefiksu PL); obecność numeru = metoda „przelew
 * tradycyjny" jest dostępna. Odbiorca domyślnie = nazwa firmy (nadpisywalny),
 * nazwa banku opcjonalna. Wszystko nullable — dane opcjonalne, jak reszta profilu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('bank_account_number', 26)->nullable()->after('default_vat_rate');
            $table->string('bank_account_holder')->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('bank_account_holder');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['bank_account_number', 'bank_account_holder', 'bank_name']);
        });
    }
};
