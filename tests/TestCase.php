<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
    }
}
