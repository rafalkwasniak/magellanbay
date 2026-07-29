<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adres wypisu w stopce wiadomości. Wyłącznie dla korespondencji seryjnej —
 * maile transakcyjne (potwierdzenie zamówienia, zmiana statusu, link
 * aktywacyjny) zostawiają to pole puste i NIE dostają stopki wypisu, bo są
 * niezbędne do wykonania umowy i nie da się z nich „wypisać".
 *
 * Osobna kolumna zamiast doklejania linku do treści: wypis to element stopki,
 * a nie zdanie w środku wiadomości. Trzymany osobno da się później podać także
 * w nagłówku `List-Unsubscribe`, którego skrzynki używają do jednoklikowego
 * wypisu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('unsubscribe_url', 512)->nullable()->after('action_url');
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn('unsubscribe_url');
        });
    }
};
