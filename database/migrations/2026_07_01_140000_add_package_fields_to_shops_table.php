<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pakiet sklepu (model „snapshot", patrz config/shop.php i Shop::assignPackage).
 * - `package`: slug pakietu (etykieta/naklejka; nazwa PL rozwiązywana z configu).
 * - `entitlements`: JSON-snapshot uprawnień skopiowany z configu w chwili
 *   przypisania pakietu. Nullable — sklepy bez snapshotu (legacy) łapie fallback
 *   resolvera do configu. Nowe uprawnienie = nowy klucz w configu, bez migracji.
 * - `subscription_ends_at`: koniec opłaconego okresu (nullable; billing później).
 * - `comped`: sklep gratisowy/ręczny — omija wygaśnięcie i auto-zejście.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('package')->default('stall')->after('default_vat_rate');
            $table->json('entitlements')->nullable()->after('package');
            $table->timestamp('subscription_ends_at')->nullable()->after('entitlements');
            $table->boolean('comped')->default(false)->after('subscription_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['package', 'entitlements', 'subscription_ends_at', 'comped']);
        });
    }
};
