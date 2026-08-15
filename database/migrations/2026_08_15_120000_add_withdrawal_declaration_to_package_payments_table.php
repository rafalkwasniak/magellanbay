<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dowód wyraźnego żądania rozpoczęcia świadczenia przed upływem terminu
 * odstąpienia (art. 15 ust. 3 i art. 35 ustawy o prawach konsumenta).
 *
 * DLACZEGO: §9 ust. 2 Regulaminu zakłada, że pakiet aktywuje się od razu „na
 * wyraźne żądanie Sprzedawcy", i na tej podstawie przy odstąpieniu zatrzymujemy
 * zapłatę za wykorzystany okres. Bez utrwalonego żądania złożonego PRZED
 * rozpoczęciem świadczenia ta zapłata się nie należy — konsument (a przy Kramio
 * także przedsiębiorca na prawach konsumenta) odzyskuje całość. Dokument to
 * obiecywał od 06.08.2026, kod tego nie zbierał.
 *
 * Kolumny są migawką z chwili kliknięcia „Kup", tak jak kwota i termin obok:
 * później zmieniona wersja regulaminu nie może przepisać historii.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_payments', function (Blueprint $table) {
            $table->timestamp('immediate_start_at')->nullable()->after('applied_at');
            $table->string('immediate_start_ip', 45)->nullable()->after('immediate_start_at');
            $table->unsignedInteger('immediate_start_terms_version')->nullable()->after('immediate_start_ip');
        });
    }

    public function down(): void
    {
        Schema::table('package_payments', function (Blueprint $table) {
            $table->dropColumn([
                'immediate_start_at',
                'immediate_start_ip',
                'immediate_start_terms_version',
            ]);
        });
    }
};
