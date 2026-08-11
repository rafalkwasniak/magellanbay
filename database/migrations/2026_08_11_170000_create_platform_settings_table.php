<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Przełączniki OPERACYJNE platformy — te, których nie da się trzymać w `config/`,
 * bo trzeba je przestawić NATYCHMIAST, bez wgrywania kodu.
 *
 * Świadome odstępstwo od zasady „stałe biznesowe w config/" (FOUNDATION sek. 5)
 * i wąskie: config zostaje jedynym źródłem prawdy dla cennika, progów i danych
 * firmy — one zmieniają się raz na kilka lat i deploy jest przy nich w porządku.
 * Tutaj lądują wyłącznie rzeczy, które trzeba móc wyłączyć w środku awarii:
 * zamknięcie rejestracji i komunikat o przerwie technicznej.
 *
 * Klucz–wartość, bo przełączników są dwa i przez najbliższy rok będzie ich
 * kilka. Kolumna per ustawienie znaczyłaby migrację przy każdym nowym.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            // `text`, nie `string`: komunikat o przerwie bywa akapitem, a nie hasłem.
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
