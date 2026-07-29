<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akumulator zwróconych sztuk na pozycji zamówienia (prawo odstąpienia, 14 dni).
 *
 * `quantity` zostaje NIETKNIĘTE — jest migawką tego, co klient kupił, i zarazem
 * ilością zdjętą ze stanu przy składaniu (edytor zamówienia liczy z niej sufit
 * magazynowy). Zwrot dopisuje się obok, więc zamówienie kurczy się w kwotach,
 * a historia zostaje: „kupił 3, oddał 1".
 *
 * Zwracalne = `quantity − returned_quantity`, dzięki czemu drugi zwrot widzi
 * tylko resztę i nie da się oddać trzech doniczek w nieskończoność.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('returned_quantity', 10, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });
    }
};
