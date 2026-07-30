<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opłaty za pakiety Kramio — płatność sprzedawcy DO PLATFORMY (konto Paynow
 * platformy z `.env`, nie integracja sklepu).
 *
 * Wiersz to MIGAWKA wyceny z chwili kliknięcia „Kup": pakiet docelowy, kwota,
 * zniżka za resztówkę i nowy termin. Webhook stosuje TĘ migawkę, niczego nie
 * przelicza — kwota zapłacona i kwota obiecana nie mogą się rozjechać, nawet
 * gdy cennik zmieni się między kliknięciem a wpłatą.
 *
 * `payment_id` = identyfikator płatności w Paynow (po nim webhook odnajduje
 * wiersz). `status`: pending → paid / failed. `applied_at` = kiedy pakiet
 * faktycznie ustawiono (idempotencja webhooka — drugi CONFIRMED nic nie robi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('target_package', 32);
            $table->decimal('amount', 10, 2);
            $table->decimal('credit', 10, 2)->default(0);
            $table->timestamp('new_ends_at');

            $table->string('status', 16)->default('pending');
            $table->string('payment_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_payments');
    }
};
