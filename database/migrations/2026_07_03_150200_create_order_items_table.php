<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pozycje zamówienia — migawka każdej pozycji z chwili złożenia (nazwa, cena
 * brutto, stawka VAT, ilość, wartość). `product_id` nullable: produkt może
 * zostać później miękko usunięty, a zamówienie ma pozostać wierne temu, co
 * faktycznie kupiono (nie odczytujemy ceny/nazwy z aktualnego produktu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->decimal('unit_price_gross', 10, 2);
            $table->string('vat_rate', 3);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total_gross', 10, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
