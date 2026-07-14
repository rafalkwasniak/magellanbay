<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migracja DANYCH, nie schematu. Ścieżka statusów przy przedpłacie (`OrderFlow`)
 * nie zna statusu „Nowe" — zamówienie z przelewem startuje od razu w „Oczekuje
 * na płatność". Zamówienia złożone wcześniej dostawały sztywne „Nowe", więc bez
 * tej poprawki wypadłyby poza własną ścieżkę i panel nie zaproponowałby im
 * żadnego kolejnego kroku.
 *
 * Nie dopisujemy zdarzenia na oś czasu: to nie jest zmiana statusu, którą zrobił
 * sprzedawca, tylko korekta zapisu do stanu, w jakim te zamówienia powinny były
 * powstać. Po migracji wyglądają dokładnie jak świeżo złożone zamówienie z
 * przelewem (status bez poprzedzającego zdarzenia).
 *
 * Wartości wpisane wprost, nie przez enumy — migracja ma działać także wtedy,
 * gdy kiedyś zmienimy nazwy case'ów w kodzie.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'new')
            ->update(['status' => 'awaiting_payment']);
    }

    public function down(): void
    {
        // Świadomie pusto: nie da się odróżnić zamówień przestawionych tą migracją
        // od tych, które w „Oczekuje na płatność" znalazły się normalnie. Cofnięcie
        // wrzuciłoby te drugie w status spoza ich ścieżki — gorzej niż nic.
    }
};
