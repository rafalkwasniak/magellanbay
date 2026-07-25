<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kody rabatowe sklepu (uprawnienie `discount_codes`, pakiet Pawilon).
 *
 * Świadomie BEZ osobnej tabeli „wykorzystań": liczba użyć to policzone
 * zamówienia z danym kodem (z pominięciem anulowanych — scope `countedAsSale`).
 * Mniej tabel do synchronizowania, a anulowane zamówienie samo oddaje użycie.
 *
 * `customer_id` = kod imienny (rekompensata dla konkretnego klienta); NULL =
 * kod ogólnodostępny. `product_id` wypełnione tylko przy zakresie `product`.
 * `max_uses` NULL = bez limitu, 1 = kod jednorazowy. Daty NULL = bezterminowo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('code', 32);
            $table->string('type', 20);
            // NULL przy darmowej wysyłce — jej „wartość" wynika z kosztu dostawy.
            $table->decimal('value', 10, 2)->nullable();

            $table->string('scope', 20);
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();

            // Próg liczony ZAWSZE od wartości produktów, nigdy z wysyłką.
            $table->decimal('min_items_total', 10, 2)->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();

            // Kod imienny — znika razem z kontem klienta, bo bez niego nie ma sensu.
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Ten sam kod może istnieć w wielu sklepach, ale raz w obrębie jednego.
            $table->unique(['shop_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
