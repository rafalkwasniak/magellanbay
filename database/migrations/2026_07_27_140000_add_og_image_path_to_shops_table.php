<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grafika sklepu do social mediów (Open Graph, 1200×630). Generowana raz —
 * przy zmianie logo, nazwy albo kolorów — i zapisana jako plik, bo składanie
 * obrazka przy każdym żądaniu byłoby absurdem na shared hoście.
 *
 * Osobna kolumna, a nie „logo w innym rozmiarze": logo bywa wąskie albo
 * przezroczyste i na Facebooku wygląda jak pomyłka. Tu mamy pełną kontrolę nad
 * kadrem, marginesami i tłem.
 *
 * W przyszłości ta sama kolumna przyjmie grafikę wgraną przez sprzedawcę
 * (rozważany dodatek pakietowy) — wtedy generowanie po prostu jej nie nadpisze.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('og_image_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('og_image_path');
        });
    }
};
