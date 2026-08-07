<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dwie sprawy wokół przesyłek, obie profilaktyczne — robione, póki tabela jest
 * mała i migracja trwa mgnienie.
 *
 * 1. INDEKS. Komenda `shipments:refresh` chodzi CO MINUTĘ i pyta o zamówienia
 *    z `shipment_id IS NOT NULL`. Bez indeksu każdy przebieg to pełne przejście
 *    tabeli `orders` — niewidoczne przy kilku zamówieniach, kosztowne przy
 *    kilkudziesięciu tysiącach na shared-hostingu. Kolumna jest z natury rzadka
 *    (NULL przy odbiorze osobistym, kurierze i sklepach bez integracji), więc
 *    indeks jest bardzo selektywny.
 *
 * 2. `shipment_queued_at` — własny znacznik chwili zlecenia nadania. Wcześniej
 *    odblokowanie zadań, które utknęły w kolejce, opierało się na `updated_at`,
 *    a ten podbija KAŻDY zapis zamówienia (np. edycja notatki przez sprzedawcę)
 *    i przesuwał okno wykrywania. Osobna kolumna mierzy dokładnie to, co trzeba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('shipment_queued_at')->nullable()->after('shipment_error');
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipment_id']);
            $table->dropColumn('shipment_queued_at');
        });
    }
};
