<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wiadomość platformy do SPRZEDAWCÓW — odpowiednik korespondencji seryjnej
 * sklepu (`bulk_mailings`), ale świadomie OSOBNA tabela i osobna ścieżka.
 *
 * Dlaczego nie wspólny byt z `bulk_mailings`: tamten ma `shop_id` NOT NULL,
 * bramkę pakietu, karencję, promowany produkt i wypis podpisany subdomeną
 * sklepu. Tu nie ma sklepu, nie ma pakietu, nie ma karencji i nie ma produktu.
 * Dorabianie wyjątków w działającym module sprzedawcy kosztowałoby więcej
 * niż dwie bliźniacze tabele — a ryzyko szłoby na moduł, który już zarabia.
 *
 * `recipient_ids` to WYBÓR administratora: konkretne konta zaznaczone
 * checkboxami. Zgoda marketingowa jest osobną bramką sprawdzaną dopiero przy
 * wysyłce — zaznaczenie kogoś nie omija jego wypisu.
 *
 * `recipients_count` to migawka z chwili wysyłki: do ilu naprawdę poszło.
 * Później nie do odtworzenia, bo ludzie się wypisują.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_mailings', function (Blueprint $table) {
            $table->id();

            $table->string('subject');
            $table->text('body');

            // Zaznaczone konta. Null = jeszcze nie wybierano; pusta tablica =
            // świadomie odznaczono wszystkich. Ta różnica steruje komunikatem
            // na ekranie, więc nie sprowadzamy jej do jednego stanu.
            $table->json('recipient_ids')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->nullable();
            $table->unsignedInteger('test_sends')->default(0);

            $table->timestamps();

            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_mailings');
    }
};
