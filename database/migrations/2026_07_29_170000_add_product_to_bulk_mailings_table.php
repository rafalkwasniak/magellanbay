<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produkt promowany w wiadomości — pod treścią pojawia się jego karta
 * (zdjęcie, nazwa, cena, zajawka, przycisk do sklepu).
 *
 * `nullOnDelete`: skasowanie produktu z katalogu nie może wywrócić historii
 * wysyłek. Wysłane maile i tak niosą MIGAWKĘ karty, więc nic z nich nie znika
 * — po usunięciu produktu przestaje działać wyłącznie link, i tego się nie da
 * uniknąć.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_mailings', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('body')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bulk_mailings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
