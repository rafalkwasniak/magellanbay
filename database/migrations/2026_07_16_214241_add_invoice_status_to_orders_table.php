<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stan przejściowy generowania faktury (poza `invoice_id`, który znaczy „gotowe").
 * `pending` = job w kolejce/robocie (UI: „FV w przygotowaniu", przycisk zablokowany),
 * `failed` = ostatnia próba się nie powiodła (UI: komunikat + możliwość ponowienia).
 * NULL = brak akcji albo już wystawiona (rozróżnia je `invoice_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_status')->nullable()->after('invoiced_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('invoice_status');
        });
    }
};
