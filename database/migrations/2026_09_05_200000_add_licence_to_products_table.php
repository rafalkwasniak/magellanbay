<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licencja za logotyp AWERSU — przy produkcie, nie przy wyborze kupującego.
 *
 * WPROST ZE SPECYFIKACJI KLIENTA: „tablica produktów … nr firmy do której
 * dowiązana jest ewentualna licencja na logotyp, koszt ewentualnej licencji".
 *
 * DLACZEGO TO NIE JEST OPCJA DO WYBRANIA. Kupujący nie wybiera logotypu na
 * awersie — magnes JUŻ go ma. „Metalowy magnes z oficjalnym logotypem 7 Maraton
 * Wałbrzych" to jedna pozycja katalogu, a nie magnes plus dokupiony znak.
 * Wybór dotyczy wyłącznie grawerki na rewersie.
 *
 * Model, w którym logotyp był grupą opcji, dawał się klikać, ale opisywał inny
 * sklep: taki, w którym klient dobiera sobie cudze znaki towarowe do dowolnego
 * produktu. To nie tylko rozmija się z zamówieniem — to model, w którym łatwo
 * o użycie logotypu bez pokrycia w umowie licencyjnej.
 *
 * ARYTMETYKA ZOSTAJE TA SAMA. Licencja produktu wchodzi do tej samej reguły co
 * licencja grafiki graweru: „suma po licencjodawcach, maksimum wewnątrz
 * jednego". To jest dokładnie przykład 4 ze specyfikacji — 25 zł za logotyp
 * awersu i 40 zł za grafikę graweru TEGO SAMEGO organizatora dają 40 zł, nie 65.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            /*
             * `nullOnDelete`: skasowanie partnera z kartoteki nie ma prawa
             * zdjąć produktu ze sprzedaży. Zdejmuje samą opłatę, a właściciel
             * decyduje, co dalej zrobić z produktem.
             */
            $table->foreignId('licensor_id')->nullable()->after('vat_rate')
                ->constrained('licensors')->nullOnDelete();

            $table->decimal('licence_fee_gross', 10, 2)->default(0)->after('licensor_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('licensor_id');
            $table->dropColumn('licence_fee_gross');
        });
    }
};
