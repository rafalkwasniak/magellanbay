<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ilość pozycji zamówienia z unsignedInteger → decimal(10,2), by unieść wagę
 * (np. 2,50 kg). Migawka pozostaje wierna: historyczne zamówienia miały ilości
 * całkowite, które mieszczą się w decimal bez zmiany wartości. Zamrażamy też
 * `sale_unit` pozycji — mail i panel Zamówień pokażą „2,50 kg" niezależnie od
 * późniejszej zmiany jednostki na produkcie (default 'piece' dla historii).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->change();
            $table->string('sale_unit', 16)->default('piece')->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('sale_unit');
            $table->unsignedInteger('quantity')->change();
        });
    }
};
