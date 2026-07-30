<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log zmian pakietu sklepu — pełna historia, nie tylko opłaty.
 *
 * Powód: pakiet zmienia się DWIEMA drogami — przez płatność sprzedawcy i przez
 * konsolę admina (deal, gest, korekta). Ekran „Mój pakiet" pokazywał wyłącznie
 * płatności, więc ręcznie nadany pakiet wyglądał, jakby wziął się z powietrza.
 *
 * `source`: 'payment' (kupiony) albo 'admin' (nadany ręcznie). Przy płatności
 * `package_payment_id` wiąże wpis z opłatą i fakturą; przy nadaniu ręcznym jest
 * null i nie ma kwoty — bo jej nie było.
 *
 * Wpis jest MIGAWKĄ stanu po zmianie (pakiet, cena, termin) — dokument
 * historyczny, którego późniejsze zmiany nie ruszają.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_payment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('package', 32);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->timestamp('ends_at')->nullable();
            $table->string('source', 16);
            $table->boolean('comped')->default(false);

            $table->timestamps();

            $table->index(['shop_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_changes');
    }
};
