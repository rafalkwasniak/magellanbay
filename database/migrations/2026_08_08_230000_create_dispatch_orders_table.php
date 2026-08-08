<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zlecenie odbioru — jeden przyjazd kuriera InPostu po WIELE paczek naraz.
 *
 * Dlaczego osobna tabela, a nie kolumna na zamówieniu: zlecenie z natury łączy
 * wiele przesyłek (o to w nim chodzi — dopłata jest za PRZYJAZD, nie za paczkę),
 * ma własny cykl życia i własny status.
 *
 * KLUCZOWE: `POST /dispatch_orders` zwraca 201, ale InPost weryfikuje zlecenie
 * ASYNCHRONICZNIE i potrafi odrzucić je po chwili (`status: rejected`, powód w
 * `errors`) — np. gdy któraś paczka była zadeklarowana jako „wrzucę do
 * paczkomatu". Dlatego trzymamy `status` i `error`: bez odpytywania sprzedawca
 * czekałby na kuriera, który nigdy nie przyjedzie. Zweryfikowane na sandboxie
 * 2026-08-08.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            // Identyfikator po stronie ShipX. Null tylko w oknie między naszym
            // zapisem a odpowiedzią InPostu.
            $table->unsignedBigInteger('shipx_id')->nullable();
            $table->string('status', 30)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            // Cron dopytuje o zlecenia jeszcze nierozstrzygnięte.
            $table->index(['status', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('dispatch_order_id')->nullable()->after('shipment_weight_kg')
                ->constrained('dispatch_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispatch_order_id');
        });

        Schema::dropIfExists('dispatch_orders');
    }
};
