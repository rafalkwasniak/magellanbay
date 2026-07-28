<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opis SEO strony tekstowej („Informacje"). WYŁĄCZNIE ręczny — bez generowania
 * przez AI, świadomie: to najczęściej Regulamin i Polityka prywatności, których
 * nikt nie szuka w Google, a bywają na kilkanaście tysięcy znaków, więc każde
 * wywołanie modelu byłoby drogie i bez zwrotu. Sprzedawca, któremu zależy na
 * stronie „O nas" czy „Dostawa", wpisze opis sam.
 *
 * Dlatego NIE ma tu odpowiednika `meta_description_manual` (jak przy produktach
 * i sklepie): znacznik istnieje po to, by chronić tekst przed nadpisaniem przez
 * automat — a tu żadnego automatu nie ma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_description', 255)->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('meta_description');
        });
    }
};
