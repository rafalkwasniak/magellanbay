<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprzedaż na wagę: ilość produktu przestaje być liczbą całkowitą. `stock`
 * z unsignedInteger → decimal(10,2) (dotychczasowe wartości całkowite mieszczą
 * się bez straty → wstecznie zgodne). Dodajemy `sale_unit` (piece|weight) —
 * default 'piece' zachowuje obecne zachowanie każdego istniejącego produktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock', 10, 2)->nullable()->change();
            $table->string('sale_unit', 16)->default('piece')->after('track_stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sale_unit');
            $table->unsignedInteger('stock')->nullable()->change();
        });
    }
};
