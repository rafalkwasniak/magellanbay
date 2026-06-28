<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produkty sklepu. Tabela najemcy — `shop_id` scope'uje wszystko do sklepu.
 * Cena jest brutto (`price_gross`); netto i VAT wyliczamy z brutto + stawki.
 * `slug` służy SEO-URL produktu (/produkt/{id}-{slug}); kanoniczny jest `id`.
 * `track_stock` = kontrola stanu; gdy wyłączona, `stock` jest nieistotny.
 * Soft delete — produkt, który wystąpił w zamówieniu, usuwamy logicznie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price_gross', 10, 2);
            $table->string('vat_rate', 3)->default('23');
            $table->boolean('track_stock')->default(true);
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_homepage')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['shop_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
