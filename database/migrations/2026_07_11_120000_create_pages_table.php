<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strony tekstowe sklepu („Informacje") — tabela najemcy, `shop_id` scope'uje
 * wszystko do sklepu. `content` to HTML z edytora Trix (jak opis produktu/sklepu).
 * `slug` służy SEO-URL (/informacje/{id}-{slug}); kanoniczny jest `id`.
 * `position` = jedna wspólna kolejność dla menu i stopki (bez rozdziału).
 * `is_system` = strona nieusuwalna (Regulamin) — można ją tylko przestawić.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('content')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('published')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index(['shop_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
