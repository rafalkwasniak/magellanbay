<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ślad płatności online (Paynow) na zamówieniu. `payment_external_id` to
 * identyfikator płatności nadany przez Paynow (paymentId) — globalnie unikalny,
 * więc to po nim webhook odnajduje zamówienie (numer zamówienia jest unikalny
 * tylko w obrębie sklepu). `payment_status` trzyma ostatni znany status po
 * stronie operatora (NEW/PENDING/CONFIRMED/…) — surowy, nie nasz enum, bo to
 * dziennik tego, co powiedział Paynow. Oba puste dla zamówień bez płatności online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_external_id')->nullable()->after('payment_method');
            $table->string('payment_status')->nullable()->after('payment_external_id');
            $table->index('payment_external_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_external_id']);
            $table->dropColumn(['payment_external_id', 'payment_status']);
        });
    }
};
