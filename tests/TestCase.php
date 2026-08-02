<?php

namespace Tests;

use App\Jobs\GenerateShopOgImage;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dwa gardy chroniące świat zewnętrzny przed suitą testów.
     *
     * 1. SQLITE-ONLY: gdyby phpunit.xml się zepsuł albo środowisko podłożyło
     *    produkcyjne połączenie, suita pada zamiast tknąć produkcję.
     *    Patrz DB_SECURITY.md, Warstwa 3.
     *
     * 2. ŻADNYCH PRAWDZIWYCH ŻĄDAŃ HTTP. Kosztowna lekcja z 2026-07-30: test
     *    fake'ował tylko endpoint płatności Paynow, a webhook uruchamiał job
     *    faktury — kolejka `sync` wykonała go natychmiast i poszedł NA ŻYWO do
     *    Fakturowni, wystawiając ~30 realnych faktur. `Http::fake()` z tablicą
     *    wzorców przepuszcza wszystko, czego wzorzec nie obejmuje, i o tym łatwo
     *    zapomnieć.
     *
     *    `preventStrayRequests()` odwraca domyślne zachowanie: nieudawane
     *    żądanie rzuca wyjątek zamiast lecieć do internetu. Dotyczy WSZYSTKICH
     *    integracji — Fakturownia, Paynow, DeepSeek (płatne tokeny!), GUS,
     *    Discord. Test, który czegoś nie zafake'ował, teraz o tym krzyczy.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            $this->fail(
                "ABORT: tests resolved DB connection [{$driver}:{$database}] "
                .'instead of sqlite::memory:. Refusing to run to protect production.'
            );
        }

        Http::preventStrayRequests();

        $this->skipShopCardRendering();
    }

    /**
     * Grafika sklepu do social mediów NIE powstaje w testach, chyba że test
     * jawnie o nią prosi.
     *
     * `ShopObserver` zleca ją przy każdym utworzeniu sklepu, a kolejka w testach
     * jest synchroniczna — więc każdy `Shop::factory()` składałby kartę 1200×630
     * w Imagicku. Pojedynczo to ułamek sekundy i na produkcji nikogo nie boli
     * (raz na sklep), ale suita tworzy sklepy setki razy i czas rośnie z minut
     * do godzin.
     *
     * To NIE jest zamiatanie generatora pod dywan: `OgImageTest` wywołuje go
     * wprost i sprawdza wymiary, nazwę pliku oraz sprzątanie po starej wersji,
     * a zlecenie zadania ma własne testy na `Bus::assertDispatched`. Blokujemy
     * wyłącznie WYKONANIE zadania w tle, nie samo zlecenie.
     */
    private function skipShopCardRendering(): void
    {
        Bus::fake([GenerateShopOgImage::class]);
    }
}
