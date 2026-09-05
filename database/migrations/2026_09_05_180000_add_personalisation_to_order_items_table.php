<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personalizacja zapisana na POZYCJI ZAMÓWIENIA.
 *
 * Pozycja zamówienia jest MIGAWKĄ — trzyma własną kopię nazwy, ceny i stawki
 * VAT, żeby zmiana w katalogu nie przepisywała historii sprzedaży. Z tego samego
 * powodu personalizacja też musi tu wylądować: sprzedawca wycofa grafikę albo
 * przemianuje grupę, a zamówienie sprzed miesiąca ma nadal mówić, co dokładnie
 * zamówiono i co wykonano.
 *
 * DWIE KOLUMNY, BO DWA RÓŻNI ODBIORCY:
 *
 *   `personalisation` — pary „etykieta → wartość", gotowe dla CZŁOWIEKA.
 *     Wchodzą wprost w mail do klienta, panel, fakturę i arkusz produkcyjny.
 *     Zawierają nazwy z chwili zakupu, więc czyta się je bez zaglądania
 *     do katalogu — i czyta się je nawet wtedy, gdy grupy już nie ma.
 *
 *   `configuration` — znormalizowana odpowiedź MASZYNOWA (identyfikatory grup,
 *     pól i pozycji biblioteki). Potrzebna do odtworzenia zamówienia jeden do
 *     jednego: powtórzenia zakupu, reklamacji „to nie ta grafika" i wygenerowania
 *     pliku do graweru. Etykieta („Kotwica") nie wystarczy — plik produkcyjny
 *     bierze się z konkretnego rekordu.
 *
 * `personalisation_surcharge_gross` to dopłata NA JEDNOSTKĘ, już wliczona
 * w `unit_price_gross`. Trzymamy ją osobno, bo kupujący ma prawo zobaczyć,
 * za co dopłaca („cena z czterech składników" z zamówienia klienta), a przy
 * rozliczeniach z licencjodawcami trzeba umieć oddzielić cenę towaru od opłat
 * za personalizację.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('personalisation')->nullable()->after('name');
            $table->json('configuration')->nullable()->after('personalisation');
            $table->decimal('personalisation_surcharge_gross', 10, 2)
                ->default(0)->after('unit_price_gross');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['personalisation', 'configuration', 'personalisation_surcharge_gross']);
        });
    }
};
