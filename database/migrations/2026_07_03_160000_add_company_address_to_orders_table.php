<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adres firmy (rozliczeniowy, do FV) — niezależny od adresu dostawy. Zbierany
 * przy zakupie jako firma, opcjonalny, auto-uzupełniany z NIP (GUS/Biała lista).
 * Adres dostawy (`ship_*`) to osobny byt: kurier = adres, paczkomat = skrzynka,
 * odbiór = brak. Dlatego trzymamy je rozdzielnie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('company_street')->nullable()->after('company_nip');
            $table->string('company_building_number')->nullable()->after('company_street');
            $table->string('company_apartment_number')->nullable()->after('company_building_number');
            $table->string('company_postal_code')->nullable()->after('company_apartment_number');
            $table->string('company_city')->nullable()->after('company_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'company_street', 'company_building_number', 'company_apartment_number',
                'company_postal_code', 'company_city',
            ]);
        });
    }
};
