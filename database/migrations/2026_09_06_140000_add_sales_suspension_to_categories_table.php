<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wstrzymanie sprzedaży całej serii — jednym przyciskiem, wprost ze
 * specyfikacji klienta („zablokowanie sprzedaży całej serii, z komunikatem
 * o terminie wznowienia").
 *
 * SERIA TO WĘZEŁ NA OSI JEDNOKROTNEJ. Produkt należy do dokładnie jednego
 * rodzaju, więc „ta seria" ma jednoznaczne znaczenie. Na osi wielokrotnej nie
 * miałoby: magnes stojący w „Biegach" i w „UNESCO" byłby jednocześnie
 * wstrzymany i dostępny.
 *
 * ---------------------------------------------------------------------------
 * WZNOWIENIE DZIEJE SIĘ SAMO, BEZ ZADANIA W TLE
 *
 * `sales_resume_on` to nie termin, po którym coś musi zadziałać — to warunek
 * sprawdzany przy każdym pytaniu „czy wolno kupić". Data z przeszłości znaczy
 * po prostu, że sprzedaż wróciła.
 *
 * Inaczej wznowienie wisiałoby na cronie, a tu crona nie ma i mieć nie będzie
 * (CLAUDE.md sek. 2 — to bezpiecznik, nie brak). Sklep, który nie wznowił
 * sprzedaży, bo nie przeszło zadanie w tle, traci pieniądze w ciszy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->timestamp('sales_suspended_at')->nullable()->after('description');
            $table->date('sales_resume_on')->nullable()->after('sales_suspended_at');
            $table->string('suspension_note', 300)->nullable()->after('sales_resume_on');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['sales_suspended_at', 'sales_resume_on', 'suspension_note']);
        });
    }
};
