<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategorie katalogu — JEDNA tabela na wszystkie osie podziału.
 *
 * Rodzaj, tematyka i geografia to nie trzy byty, tylko jeden byt z trzema
 * ustawieniami (czy wielokrotna, czy zagnieżdżona, jaki segment adresu).
 * Ustawienia stoją w `config/catalog.php`, tu zostaje sam klucz osi — dzięki
 * czemu panel, filtry i adresy pisze się raz, a kolejny sklep dostaje własne
 * osie bez migracji.
 *
 * `parent_id` obsługuje wyłącznie osie hierarchiczne (geografia: Włochy → Rzym).
 * Na osi płaskiej zostaje NULL — nie pilnuje tego baza, tylko walidacja, bo
 * hierarchiczność jest cechą KONFIGURACJI, a ta może się zmienić bez migracji.
 *
 * Kasowanie rodzica kasuje dzieci (`cascadeOnDelete`): „Włochy" bez „Rzymu"
 * pod spodem to węzeł-sierota, którego nie da się już nigdzie pokazać.
 * Produkty tego nie tracą — pivot puszcza tylko przypisanie, sam produkt
 * zostaje w katalogu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('axis', 32);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // Slug unikalny w obrębie OSI, nie całego sklepu: „Włochy" mogą być
            // jednocześnie tematyką i miejscem, a to dwa różne węzły.
            $table->unique(['shop_id', 'axis', 'slug']);
            $table->index(['shop_id', 'axis', 'parent_id']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('categories');
    }
};
