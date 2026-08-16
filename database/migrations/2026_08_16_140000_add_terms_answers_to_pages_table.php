<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odpowiedzi z kreatora regulaminu — dane DOKUMENTU, nie profilu sklepu.
 *
 * GRANICA (decyzja Rafała, 2026-08-16): kreator tworzy dokument, nie audytuje
 * rzeczywistości. Sprzedawca może wpisać tu adres inny niż w ustawieniach sklepu
 * i to jest jego prawo — adres rejestrowy, adres do zwrotów i adres kontaktowy
 * bywają trzema różnymi rzeczami. NIC z tego NIE WRACA do `shops`.
 *
 * Dlaczego mimo to zapisujemy: żeby po poprawkach prawnika sprzedawca nie
 * przepisywał wszystkiego od zera przy ponownym wstawieniu wzoru. To pamięć
 * kreatora, nie źródło prawdy o sklepie.
 *
 * Kolumna siedzi przy STRONIE, a nie przy sklepie, bo należy do konkretnego
 * dokumentu — obok `terms_template_version`, która mówi, z której wersji wzoru
 * ta treść powstała.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('terms_answers')->nullable()->after('terms_template_version');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('terms_answers');
        });
    }
};
