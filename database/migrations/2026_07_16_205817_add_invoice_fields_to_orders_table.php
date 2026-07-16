<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ślad faktury VAT (Fakturownia) na zamówieniu. FV wystawiamy raz — `invoice_id`
 * (identyfikator w Fakturowni) jest zarazem twardym gardem idempotencji: gdy jest
 * ustawiony, drugi raz nie generujemy. `invoice_token` to publiczny token do PDF
 * (link `…fakturownia.pl/invoice/{token}.pdf`, bez naszej autoryzacji), a
 * `invoice_number` i `invoiced_at` służą prezentacji w panelu i mailu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->after('note');
            $table->string('invoice_number')->nullable()->after('invoice_id');
            $table->string('invoice_token')->nullable()->after('invoice_number');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_id', 'invoice_number', 'invoice_token', 'invoiced_at']);
        });
    }
};
