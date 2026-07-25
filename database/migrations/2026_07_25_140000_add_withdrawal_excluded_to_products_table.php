<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wyłączenie prawa odstąpienia dla produktu (art. 38 ustawy o prawach
 * konsumenta z 30 maja 2014): towary szybko psujące się (kwiaty, żywność),
 * wykonane na indywidualne zamówienie (rękodzieło) i zapieczętowane ze
 * względów higienicznych.
 *
 * Domyślnie `false` = produkt JEST objęty prawem zwrotu. Sprzedawca świadomie
 * WYŁĄCZA je tam, gdzie prawo faktycznie nie przysługuje — nigdy odwrotnie,
 * bo domyślne „bez zwrotu" byłoby cichym pozbawieniem konsumenta uprawnienia.
 *
 * Flaga siedzi na produkcie, bo w jednym zamówieniu bywa i stroik z żywych
 * kwiatów (wyłączony), i doniczki (objęte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('withdrawal_excluded')->default(false)->after('track_stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('withdrawal_excluded');
        });
    }
};
