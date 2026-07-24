<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analityka Poziom 2: dzienny AGREGAT ruchu per sklep — jeden wiersz na (sklep,
 * dzień), inkrementowany atomowo. Świadomie NIE tabela wiersz-na-odsłonę (zabija
 * shared host i puchnie bez końca): tu 365 wierszy/sklep/rok = nic. Zamówienia
 * już mamy w `orders` — tu tylko to, czego w bazie nie ma (ruch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('product_views')->default(0);
            $table->timestamps();

            // Jeden wiersz na sklep i dzień — klucz dla atomowego upsertu licznika.
            $table->unique(['shop_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_stats');
    }
};
