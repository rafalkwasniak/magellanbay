<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opcje produktu — silnik personalizacji.
 *
 * PISANY POD KUBEK Z IMIENIEM, NIE POD MAGNES Z LOGO MARATONU. Pierwszym
 * odbiorcą jest Magellan Bay, ale to ma być część generyczna, sprzedawalna
 * kolejnym klientom (CLAUDE.md sek. 1). Dlatego w schemacie nie ma słowa
 * „awers", „rewers" ani „grawer" — są GRUPY OPCJI, a nazwy nadaje sprzedawca.
 *
 * DWA RODZAJE GRUP, bo dwa naprawdę różne pytania do kupującego:
 *
 *   `text`   — formatka: zestaw pól tekstowych z limitami znaków.
 *              „Imię (max 12)", „Data (max 10)". Kupujący WPISUJE.
 *   `choice` — wybór jednej pozycji z biblioteki, każda z własną grafiką
 *              i dopłatą. Kupujący WSKAZUJE.
 *
 * WYKLUCZANIE GRUP (`excludes_group_id`) bierze się z realnego wymagania
 * Magellana: grawer to grafika ALBO tekst, nigdy oba. Zamiast wpisywać ten
 * przypadek na sztywno, dajemy grupom wskazać, że się wykluczają — ta sama
 * mechanika obsłuży „nadruk albo haft" i każdą inną parę.
 *
 * CENY. Dopłaty trzymamy BRUTTO, tak jak `products.price_gross` — w tym sklepie
 * wszystkie ceny są brutto i mieszanie konwencji w jednym koszyku skończyłoby
 * się groszowymi rozjazdami na fakturze. Stawki VAT dopłata NIE ma własnej:
 * dziedziczy ją po produkcie, bo usługa personalizacji jest świadczeniem
 * pomocniczym do dostawy towaru i dzieli jego stawkę.
 *
 * CZEGO TU JESZCZE NIE MA — świadomie, żeby nie zgadywać: licencjodawcy
 * (`licensor_id` przy pozycji biblioteki) dochodzą w osobnym kroku razem
 * z regułą „nie sumujemy, liczy się wyższa". To część bespoke.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            // Nazwa widoczna dla kupującego („Nadruk na froncie", „Grawer").
            $table->string('name');

            // `text` = formatka z polami, `choice` = wybór z biblioteki.
            $table->string('kind');

            // Wyjaśnienie pod nagłówkiem grupy w kasie — miejsce na zdanie
            // w rodzaju „Wpisz imię, które wygrawerujemy na odwrocie".
            $table->string('hint')->nullable();

            /*
             * Czy kupujący MUSI wypełnić tę grupę, żeby dodać produkt do koszyka.
             * Domyślnie nie: personalizacja jest zwykle dodatkiem, a wymuszona
             * na starcie zamieniłaby zwykły zakup w formularz.
             */
            $table->boolean('required')->default(false);

            /*
             * Dopłata za SAMO skorzystanie z grupy — koszt wykonania, niezależny
             * od tego, co kupujący wpisze. Przy formatce to jedyne miejsce, gdzie
             * dopłata może siedzieć (pola tekstowe nie mają własnych cen).
             */
            $table->decimal('surcharge_gross', 10, 2)->default(0);

            /*
             * Grupa, z którą ta się wyklucza — wybór w jednej wygasza drugą.
             * `nullOnDelete`, bo skasowanie jednej grupy nie ma prawa zabrać
             * drugiej; ma tylko znieść wykluczenie.
             */
            $table->foreignId('excludes_group_id')->nullable()
                ->constrained('option_groups')->nullOnDelete();

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'position']);
        });

        /*
         * Pola formatki (grupy `text`). Osobna tabela, nie JSON na grupie:
         * limit znaków jest walidowany przy każdym dodaniu do koszyka, a wartości
         * pól trafiają potem do arkusza produkcyjnego. Jedno i drugie chce
         * stabilnego identyfikatora pola, którego JSON nie daje.
         */
        Schema::create('option_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('label');

            /*
             * Limit znaków. NIE jest ozdobnikiem — wynika z fizyki produktu:
             * na magnes 70 × 50 mm wchodzi tyle liter, ile wchodzi. Przekroczenie
             * to zamówienie niewykonalne, więc limit jest twardą walidacją,
             * a nie podpowiedzią.
             */
            $table->unsignedSmallInteger('max_length')->default(30);

            $table->boolean('required')->default(true);
            $table->string('placeholder')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['option_group_id', 'position']);
        });

        /*
         * Pozycje biblioteki (grupy `choice`) — gotowe grafiki albo warianty
         * z własną dopłatą.
         */
        Schema::create('option_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('image_path')->nullable();
            $table->decimal('surcharge_gross', 10, 2)->default(0);

            /*
             * Wycofana pozycja ZOSTAJE w bazie, tylko znika z wyboru. Kasowanie
             * unieważniłoby historyczne zamówienia, w których ktoś ją wybrał —
             * a arkusz produkcyjny i reklamacja muszą wiedzieć, co zamówiono.
             */
            $table->boolean('is_active')->default(true);

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['option_group_id', 'position']);
        });

        /*
         * Przypięcie grup do produktów — wiele do wielu, bo o to chodzi
         * w oszczędności: „Nadruk 3 linie" definiuje się RAZ i przypina do
         * stu magnesów. Zmiana limitu znaków poprawia wtedy sto kart naraz.
         */
        Schema::create('option_group_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'option_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_group_product');
        Schema::dropIfExists('option_choices');
        Schema::dropIfExists('option_fields');

        // Samoodwołanie `excludes_group_id` — klucz obcy musi zniknąć przed tabelą.
        Schema::table('option_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('excludes_group_id');
        });
        Schema::dropIfExists('option_groups');
    }
};
