<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ślad przesyłki InPost (ShipX) na zamówieniu. Bliźniak pól faktury:
 * `shipment_id` znaczy „nadana" i jest gardem idempotencji — nadajemy RAZ,
 * bo każde nadanie zdejmuje opłatę z salda sprzedawcy.
 *
 * `shipment_status` trzyma surowy status ShipX (`created`, `offer_selected`,
 * `confirmed`, …) — nie mapujemy go na własny enum, bo InPost dokłada kolejne
 * stany po drodze i własna lista rozjechałaby się przy pierwszym z nich.
 *
 * `shipment_error` niesie POLSKI komunikat dla sprzedawcy (np. „Brak środków
 * na koncie InPost”). Osobna kolumna, bo ShipX nie zgłasza nieudanego zakupu
 * błędem HTTP — powód leży w `transactions` i bez zapisania go tutaj przesyłka
 * wisiałaby w panelu bez wyjaśnienia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipment_id')->nullable()->after('invoice_status');
            $table->string('shipment_tracking_number', 64)->nullable()->after('shipment_id');
            $table->string('shipment_status', 40)->nullable()->after('shipment_tracking_number');
            $table->string('shipment_size', 10)->nullable()->after('shipment_status');
            $table->string('shipment_error')->nullable()->after('shipment_size');
            $table->timestamp('shipped_at')->nullable()->after('shipment_error');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipment_id',
                'shipment_tracking_number',
                'shipment_status',
                'shipment_size',
                'shipment_error',
                'shipped_at',
            ]);
        });
    }
};
