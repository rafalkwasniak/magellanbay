<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wersja wzoru regulaminu, na którym opiera się treść podstrony.
 *
 * DLACZEGO OD RAZU, a nie „później, jak będzie potrzeba": wzór trafi do
 * przeglądu prawnika i najpewniej wróci z poprawkami. Bez tej kolumny nie da się
 * ustalić, którzy sprzedawcy opublikowali starą wersję — a dorobić tego wstecz
 * nie sposób, bo treść w podstronie jest już wtedy zredagowana przez sprzedawcę
 * i nie da się jej porównać z żadnym wzorcem.
 *
 * NULL = treść własna sprzedawcy albo zaślepka; liczba = wersja `SellerTerms::VERSION`
 * z chwili, gdy wstawił wzór i zapisał stronę.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->unsignedSmallInteger('terms_template_version')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('terms_template_version');
        });
    }
};
