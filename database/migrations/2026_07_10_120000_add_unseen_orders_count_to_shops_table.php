<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licznik „nowych zamówień" na sklepie — powiadomienie „coś wpadło, odkąd nie
 * zaglądałeś": +1 przy złożeniu zamówienia, zero przy wejściu na listę Zamówień.
 * Trzymany na sklepie (nie na userze), bo panel jest scope'owany do sklepu; badge
 * czyta gotową kolumnę z już-załadowanego obiektu, bez liczenia po statusie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedInteger('unseen_orders_count')->default(0)->after('last_order_number');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('unseen_orders_count');
        });
    }
};
