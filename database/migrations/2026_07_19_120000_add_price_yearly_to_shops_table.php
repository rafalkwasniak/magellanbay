<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cena roczna sklepu (model „snapshot", jak `entitlements`). BRUTTO (VAT 23%),
 * w złotych — spójnie z resztą kwot w aplikacji (`decimal(10,2)`, *_gross).
 *
 * Kopiowana z `config/shop.php` (`packages.{slug}.price_yearly`) w chwili
 * przypisania pakietu (Shop::assignPackage). Trzymana per sklep, bo:
 * - admin może ustawić indywidualną cenę dla konkretnego sprzedawcy (deal),
 * - przy odnowieniu cena idzie za aktualnym cennikiem NIEZALEŻNIE od uprawnień
 *   (te są „lepkie"), więc musi być osobną, edytowalną wartością — nie czytaną
 *   żywcem z configu.
 *
 * Nullable: sklepy bez snapshotu (legacy) łapie fallback resolvera do configu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->decimal('price_yearly', 10, 2)->nullable()->after('entitlements');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('price_yearly');
        });
    }
};
