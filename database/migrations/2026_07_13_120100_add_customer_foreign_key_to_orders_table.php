<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domyka rezerwację z migracji zamówień: `orders.customer_id` (istniejąca,
 * nullable, zaindeksowana) dostaje klucz obcy do `customers`. Zamówienie gościa
 * zostaje z `customer_id = null`; przy usunięciu konta klienta zamówienia NIE
 * znikają — `nullOnDelete` odpina je do gościa (historia i numeracja per-sklep
 * są nienaruszalne). Indeks na kolumnie już istnieje z migracji zamówień.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
    }
};
