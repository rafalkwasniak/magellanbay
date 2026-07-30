<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sprawdza, że garda z TestCase FAKTYCZNIE blokuje wyjście w świat. Bez tego
 * testu wierzylibyśmy w ochronę, której nie ma — a to ona stoi między suitą
 * a realnymi fakturami w KSeF i płatnymi tokenami AI.
 */
class StrayGuardTest extends TestCase
{
    public function test_unfaked_request_is_blocked(): void
    {
        $this->expectException(\Illuminate\Http\Client\StrayRequestException::class);

        Http::get('https://redpaprika.fakturownia.pl/invoices.json');
    }

    public function test_partial_fake_still_blocks_other_hosts(): void
    {
        // Dokładnie pułapka z 2026-07-30: fake obejmuje jeden endpoint,
        // a kod woła drugi. Wcześniej ten drugi leciał na żywo.
        Http::fake(['*/v1/payments' => Http::response(['paymentId' => 'X'])]);

        $this->expectException(\Illuminate\Http\Client\StrayRequestException::class);

        Http::post('https://redpaprika.fakturownia.pl/invoices.json');
    }
}
