<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil sklepu (kategoria „profil i adres" z planu ustawień): opis + dane
 * adresowe w OSOBNYCH, walidowanych polach — spec wymaga osobnych pól, bo adres
 * jest używany w regulaminie, dokumentach i przyszłych integracjach.
 *
 * Pola nullable, bo sklep powstaje jako szkic przy rejestracji bez tych danych;
 * sprzedawca uzupełnia je w panelu (krok „Uzupełnij dane sklepu"). Nazwa sklepu
 * (`name`) pozostaje edytowalna, ale slug/subdomena są stałe (zabetonowane przy
 * rejestracji) — edycja nazwy ich nie rusza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('country')->default('Polska')->after('status');
            $table->string('province')->nullable()->after('country');     // województwo
            $table->string('city')->nullable()->after('province');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('street')->nullable()->after('postal_code');
            $table->string('building_number')->nullable()->after('street');
            $table->string('apartment_number')->nullable()->after('building_number');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'country', 'province', 'city',
                'postal_code', 'street', 'building_number', 'apartment_number',
            ]);
        });
    }
};
