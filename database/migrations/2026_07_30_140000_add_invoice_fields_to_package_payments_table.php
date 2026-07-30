<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ślad faktury Kramio za pakiet. `invoice_id` jest zarazem gardem
 * idempotencji — FV wystawiamy DOKŁADNIE RAZ na opłatę, a webhooki się
 * dublują. `invoice_token` daje publiczny link do PDF (bez api_token),
 * ten sam mechanizm co przy fakturach sprzedawcy dla jego klientów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_payments', function (Blueprint $table) {
            $table->string('invoice_id')->nullable()->after('applied_at');
            $table->string('invoice_number')->nullable()->after('invoice_id');
            $table->string('invoice_token')->nullable()->after('invoice_number');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_token');
        });
    }

    public function down(): void
    {
        Schema::table('package_payments', function (Blueprint $table) {
            $table->dropColumn(['invoice_id', 'invoice_number', 'invoice_token', 'invoiced_at']);
        });
    }
};
