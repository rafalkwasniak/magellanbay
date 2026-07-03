<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licznik numeracji zamówień per-sklep (spec „Numeracja zamówień"). Monotoniczny,
 * niezależny od istnienia wierszy zamówień — dzięki temu numery są ciągłe i nigdy
 * nie są odzyskiwane (anulowanie/usunięcie logiczne nie zwalnia numeru). Numer
 * alokujemy atomowo przez blokadę wiersza sklepu (Shop::allocateOrderNumber).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedInteger('last_order_number')->default(0)->after('comped');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('last_order_number');
        });
    }
};
