<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zdjęcia produktu (max 5: 1 główne + 4 dodatkowe). `position` ustala kolejność;
 * zdjęcie o najniższej pozycji jest główne. Pliki na dysku `public`; oryginały
 * nie są przechowywane (zapisujemy zoptymalizowaną wersję bez metadanych).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
