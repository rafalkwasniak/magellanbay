<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motyw sklepu — wybór szablonu i palety storefrontu (patrz config/themes.php,
 * Shop::templateSlug()/themeTokens()). Model referencji, jak przy pakietach:
 * - `template`: slug wybranego szablonu (naklejka; nazwa PL rozwiązywana z configu).
 *   Default = domyślny szablon; fallback resolvera łapie slug nieobecny w configu.
 * - `theme`: JSON z wyborami sprzedawcy (na razie: wybrana paleta). Nullable —
 *   brak = domyślna paleta szablonu. Nowe wybory = nowy klucz w JSON, bez migracji.
 * Wygląd/kolory są dla WSZYSTKICH pakietów, więc to NIE jest uprawnienie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('template')->default('velvet_cloud')->after('logo_path');
            $table->json('theme')->nullable()->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['template', 'theme']);
        });
    }
};
