<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migawka karty promowanego produktu w wysłanej wiadomości: nazwa, cena, cena
 * sprzed obniżki, zajawka, adres zdjęcia i link do sklepu.
 *
 * Dlaczego migawka, a nie odczyt z produktu przy renderowaniu: mail jest
 * statyczny i musi pokazywać to, co pokazywał w dniu wysyłki. Cena w mailu to
 * informacja handlowa — gdyby czytała się z bazy, wiadomość sprzed miesiąca
 * pokazywałaby dzisiejszą cenę, a klient miałby prawo czuć się wprowadzony
 * w błąd. Ta sama zasada, co przy pozycjach zamówienia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->json('product_card')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn('product_card');
        });
    }
};
