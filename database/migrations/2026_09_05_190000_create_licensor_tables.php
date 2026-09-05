<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licencjodawcy i rozbicie ceny pozycji zamówienia.
 *
 * TO JEST CZĘŚĆ BESPOKE. Silnik opcji (krok 1) i koszyk per konfiguracja
 * (krok 2) są generyczne i mają się kiedyś sprzedać kolejnym klientom. Kartoteka
 * firm inkasujących opłatę za użycie logotypu jest właściwym modelem biznesowym
 * TEGO sklepu i zostaje przy nim (CLAUDE.md sek. 1).
 *
 * ---------------------------------------------------------------------------
 * DLACZEGO OPŁATA LICENCYJNA JEST OSOBNĄ KWOTĄ, A NIE CZĘŚCIĄ DOPŁATY
 *
 * Bo rządzi nią inna arytmetyka. Zwykłe dopłaty się SUMUJĄ, opłaty licencyjne
 * — nie zawsze: dwie licencje TEJ SAMEJ firmy nie sumują się, liczy się wyższa;
 * licencje RÓŻNYCH firm sumują się normalnie (ustalenie z klientem 05.09).
 * Trzymane w jednej kolumnie z kosztem wykonania graweru nie dałyby się rozdzielić
 * w chwili liczenia ceny — ani później, przy rozliczeniu z partnerem.
 *
 * ---------------------------------------------------------------------------
 * DLACZEGO ROZBICIE W OSOBNYCH WIERSZACH, A NIE W JSON-ie
 *
 * Bo pytanie, które ten moduł ma obsłużyć, brzmi: „ile sztuk sprzedano z logo
 * Biegu Gdańskiego w marcu i ile się im należy". Po wierszach to jedno zapytanie
 * z sumowaniem; po JSON-ie — przemiał wszystkich zamówień w PHP, rosnący razem
 * ze sklepem. Skoro rozliczenia z licencjodawcami są w zamówieniu klienta,
 * wybieramy kształt, który na nie odpowiada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            // Nazwa firmy — organizator biegu, klub, wydawca. Widoczna wyłącznie
            // dla sprzedawcy: kupujący widzi opłatę, nie kartotekę partnerów.
            $table->string('name');

            $table->string('contact_email')->nullable();
            $table->string('contact_person')->nullable();

            // Numer umowy licencyjnej — przy sporze to pierwsza rzecz, o którą
            // pyta partner („na jakiej podstawie użyliście naszego logo").
            $table->string('agreement_reference')->nullable();

            $table->text('notes')->nullable();

            /*
             * Wycofanego partnera NIE KASUJEMY, tylko gasimy: rozliczenia
             * historyczne muszą dalej wskazywać, komu należały się pieniądze
             * za sprzedaż sprzed roku.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['shop_id', 'name']);
        });

        Schema::table('option_choices', function (Blueprint $table) {
            /*
             * `nullOnDelete`: skasowanie partnera nie ma prawa zabrać grafiki
             * z biblioteki. Zdejmuje tylko opłatę licencyjną, a sprzedawca
             * decyduje, czy grafika zostaje w sprzedaży.
             */
            $table->foreignId('licensor_id')->nullable()->after('option_group_id')
                ->constrained('licensors')->nullOnDelete();

            /*
             * Opłata licencyjna, ODDZIELNIE od `surcharge_gross`. Ta pierwsza
             * należy się partnerowi i podlega regule „suma po firmach, maksimum
             * wewnątrz jednej"; ta druga jest kosztem sprzedawcy i sumuje się
             * zwyczajnie.
             */
            $table->decimal('licence_fee_gross', 10, 2)->default(0)->after('surcharge_gross');
        });

        Schema::create('order_item_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            // `product` | `option` | `licence` — patrz App\Enums\PriceComponentKind.
            $table->string('kind');

            // Etykieta z chwili zakupu. Pozycja zamówienia jest migawką, więc
            // rozbicie też musi się czytać bez zaglądania do katalogu.
            $table->string('label');

            /*
             * Partner, któremu należy się ta kwota. `nullOnDelete` z tego samego
             * powodu co wyżej — skasowanie kartoteki nie ma prawa uszkodzić
             * historii sprzedaży. `licensor_name` jest migawką NAZWY, żeby raport
             * dało się przeczytać nawet po skasowaniu wiersza.
             */
            $table->foreignId('licensor_id')->nullable()
                ->constrained('licensors')->nullOnDelete();
            $table->string('licensor_name')->nullable();

            // Kwota NA JEDNOSTKĘ. Ilość stoi na pozycji zamówienia i nie ma
            // powodu jej tu powtarzać — powtórzona rozjechałaby się przy edycji
            // zamówienia.
            $table->decimal('unit_amount_gross', 10, 2);

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['order_item_id', 'position']);
            $table->index('licensor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_components');

        Schema::table('option_choices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('licensor_id');
            $table->dropColumn('licence_fee_gross');
        });

        Schema::dropIfExists('licensors');
    }
};
