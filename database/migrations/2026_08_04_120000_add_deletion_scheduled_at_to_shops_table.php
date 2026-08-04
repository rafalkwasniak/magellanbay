<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Karencja przed usunięciem sklepu. Obecność daty = sprzedawca zlecił usunięcie
 * i biegnie mu 7 dni na rozmyślenie się; storefront gaśnie od razu, a właściwe
 * kasowanie robi `shops:purge` po tym terminie.
 *
 * Świadomie zwykła kolumna, nie status w `ShopStatus` — tamten enum opisuje
 * WIDOCZNOŚĆ napędzaną produktami (ProductObserver ustawia go sam), więc
 * dołożenie tam stanu administracyjnego zderzyłoby się z automatem.
 *
 * Nie jest mass-assignable: termin ustawia wyłącznie `App\Services\ShopEraser`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('deletion_scheduled_at')->nullable()->after('comped')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('deletion_scheduled_at');
        });
    }
};
