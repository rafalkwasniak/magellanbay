<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domyślna jednostka sprzedaży sklepu — typowane pole na `shops` (jak
 * `default_vat_rate`). Formularz nowego produktu prefilluje się tą wartością,
 * a produkt może ją nadpisać (warzywniak: ziemniaki na kg, jajka na szt.).
 * Spójne z App\Enums\SaleUnit; default 'piece' = obecne zachowanie sklepów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('default_sale_unit', 16)->default('piece')->after('default_vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('default_sale_unit');
        });
    }
};
