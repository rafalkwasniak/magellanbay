<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wyróżnienie strony tekstowej kafelkiem na stronie głównej — pod promowanymi
 * produktami stają promowane treści (sprzedawca może opowiedzieć o sobie,
 * wywiadach czy spotkaniu autorskim, nie tylko wystawić towar).
 *
 * Nazwa kolumny celowo identyczna jak na `products` — to samo pojęcie, więc to
 * samo słowo. Domyślnie `false`: żaden istniejący sklep nic nie traci ani nie
 * zyskuje w dniu wdrożenia, efekt włącza się dopiero po odhaczeniu w panelu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('show_on_homepage')->default(false)->after('published');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('show_on_homepage');
        });
    }
};
