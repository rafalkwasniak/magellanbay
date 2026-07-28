<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licznik zadań AI zużytych przez sklep w oknie rozliczeniowym. Jeden wiersz na
 * (sklep, tydzień), inkrementowany atomowo — ten sam wzorzec co `shop_stats`.
 *
 * `period` to numer tygodnia ISO w formacie `2026-W31`: tydzień zaczyna się w
 * poniedziałek i nie rozjeżdża się na przełomie roku (zwykły numer tygodnia
 * potrafi dać „tydzień 53" albo „0"). Trzymamy tekstem, bo klucz ma być czytelny
 * przy zaglądaniu do bazy i niezależny od strefy czasowej serwera bazy.
 *
 * Wierszy nie kasujemy: to grosze miejsca (52 na sklep rocznie), a historia
 * pokazuje, ile sklep faktycznie korzysta z AI — przyda się przy ustalaniu
 * docelowych limitów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('period', 8);              // np. 2026-W31
            $table->unsignedInteger('tasks')->default(0);
            $table->timestamps();

            // Jeden wiersz na sklep i okno — klucz dla atomowego upsertu.
            $table->unique(['shop_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
