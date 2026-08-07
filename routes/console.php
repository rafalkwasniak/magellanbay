<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Outbox maili: krótki proces co minutę (bezpieczne na shared-hoście, nie demon).
// Wymaga wpisu crona na serwerze: * * * * * php artisan schedule:run
Schedule::command('email:dispatch')->everyMinute()->withoutOverlapping();

// Kolejka zadań w tle (na razie: wystawianie faktur VAT). Świadomie NIE demon
// `queue:work`, lecz krótki bieg, który KOŃCZY się, gdy kolejka pusta — na idle
// wychodzi natychmiast, obciążając LVE tylko realną robotą. `--max-time` domyka
// proces przed kolejną minutą; `withoutOverlapping` nie pozwala się nakładać.
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping();

// Przesyłki InPost: dopytanie o stan świeżo nadanych paczek (zakup u InPostu
// jest asynchroniczny — numer do śledzenia i etykieta pojawiają się chwilę po
// nadaniu). Co minutę, bo sprzedawca stoi nad panelem i czeka na etykietę;
// zapytanie leci tylko dla przesyłek jeszcze nieopłaconych, więc zwykle zero.
Schedule::command('shipments:refresh')->everyMinute()->withoutOverlapping();

// Doręczenia: raz na godzinę pytamy o paczki w drodze, żeby zapisać DATĘ ODBIORU
// (dla paczkomatu = moment wyjęcia paczki przez klienta). Od niej liczy się
// ustawowe 14 dni na odstąpienie. Rzadko, bo nikt nie czeka nad ekranem,
// a paczka potrafi leżeć w skrytce kilka dni.
Schedule::command('shipments:refresh --deliveries')->hourly()->withoutOverlapping();

// Abonamenty: przypomnienia przed terminem i zamek po karencji. Raz na dobę o
// świcie — maile idą przez outbox, więc godzina jest tylko chwilą, w której
// wpadają do kolejki. Komenda jest idempotentna, więc powtórka nic nie psuje.
Schedule::command('subscriptions:check')->dailyAt('06:10')->withoutOverlapping();

// Usuwanie sklepów: kasuje te po karencji i zwalnia adresy po kwarantannie.
// Tuż po abonamentach, bo obie komendy są dobowe i nie mają na siebie wpływu.
Schedule::command('shops:purge')->dailyAt('06:20')->withoutOverlapping();
