<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zamówienia. Kluczowe zasady ze specyfikacji:
 * - `number` = numer per-sklep (od 1, ciągły, nieodzyskiwany) — osobny od id.
 * - Migawka danych: kupujący, firma, adres dostawy, metody i sumy zapisane w
 *   chwili złożenia — zamówienie ma być wierne, nawet gdy produkt/dane się zmienią.
 * - Tylko usuwanie logiczne (softDeletes) — numery i historia zostają.
 * `customer_id` zarezerwowane pod moduł kont klientów (na teraz gość = null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('status')->default('new');

            // Migawka kupującego.
            $table->string('buyer_name');
            $table->string('buyer_surname');
            $table->string('buyer_email');
            $table->string('buyer_phone')->nullable();

            // Zakup jako firma (opcjonalny).
            $table->boolean('is_company')->default(false);
            $table->string('company_name')->nullable();
            $table->string('company_nip')->nullable();

            // Migawka adresu dostawy (przy odbiorze osobistym pusty).
            $table->string('ship_street')->nullable();
            $table->string('ship_building_number')->nullable();
            $table->string('ship_apartment_number')->nullable();
            $table->string('ship_postal_code')->nullable();
            $table->string('ship_city')->nullable();

            // Metody i koszty.
            $table->string('delivery_method');
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->string('payment_method');

            // Sumy (migawka brutto/netto/VAT + koszt dostawy + razem).
            $table->decimal('items_total', 10, 2);
            $table->decimal('total_net', 10, 2);
            $table->decimal('total_vat', 10, 2);
            $table->decimal('total_gross', 10, 2);

            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'number']);
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
