<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Treść maila jako gotowy HTML — dla wiadomości pisanych w edytorze (Trix)
 * przez sprzedawcę: korespondencja seryjna.
 *
 * Maile systemowe budujemy w kodzie z bloków (`intro_lines`), gdzie każdy
 * fragment jest escapowany przy renderowaniu. Tu jest odwrotnie: treść
 * powstaje w edytorze WYSIWYG, więc formatowanie (pogrubienia, listy,
 * nagłówki, odnośniki) musi przetrwać do skrzynki. Zamiast osłabiać
 * escapowanie wszystkich maili, dokładamy osobne pole, do którego trafia
 * wyłącznie HTML przepuszczony przez `HtmlSanitizer` na zapisie.
 *
 * Puste dla wszystkich pozostałych wiadomości — te dalej idą blokami.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->text('body_html')->nullable()->after('intro_lines');
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn('body_html');
        });
    }
};
