<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oś czasu zmian statusu zamówienia. Jeden wiersz = jedno przejście
 * (skąd → dokąd, kiedy, opcjonalna notatka sprzedawcy). Zdarzenia są
 * niezmienne (tylko created_at) — historii nie edytujemy ani nie kasujemy
 * poza kaskadą przy usunięciu zamówienia. Pierwsza linia osi („Złożone")
 * to samo `orders.created_at`, więc starych zamówień nie backfillujemy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_events');
    }
};
