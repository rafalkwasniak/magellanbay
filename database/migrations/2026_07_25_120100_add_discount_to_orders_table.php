<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ślad rabatu na zamówieniu. Ten sam wzorzec co przy fakturach: obok relacji
 * trzymamy MIGAWKĘ (`discount_code`, `discount_amount`), bo zamówienie ma
 * pamiętać, co dostało, nawet gdy sprzedawca skasuje kod albo zmieni jego
 * wartość. Relacja służy do liczenia użyć, migawka do pokazywania historii.
 *
 * `discount_amount` to kwota zdjęta z PRODUKTÓW. Darmowa wysyłka nie zapisuje
 * się tutaj — ona po prostu ustawia `delivery_cost` na 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('discount_code_id')->nullable()->after('items_total')
                ->constrained()->nullOnDelete();
            $table->string('discount_code', 32)->nullable()->after('discount_code_id');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn(['discount_code', 'discount_amount']);
        });
    }
};
