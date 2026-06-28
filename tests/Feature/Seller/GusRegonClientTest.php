<?php

namespace Tests\Feature\Seller;

use App\Services\CompanyLookup;
use App\Services\GusRegonClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GusRegonClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.gus.key' => 'test-key',
            'services.gus.base_url' => 'https://wyszukiwarkaregon.stat.gov.pl',
        ]);
    }

    /** Owija kopertę SOAP w odpowiedź MTOM (multipart/related), jak realny GUS. */
    private function mtom(string $envelope): string
    {
        return "--uuid:boundary\r\n"
            ."Content-ID: <http://tempuri.org/0>\r\n"
            ."Content-Type: application/xop+xml;charset=utf-8;type=\"application/soap+xml\"\r\n\r\n"
            .$envelope
            ."\r\n--uuid:boundary--";
    }

    private function loginEnvelope(string $sid): string
    {
        return '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">'
            .'<s:Body><ZalogujResponse xmlns="http://CIS/BIR/PUBL/2014/07">'
            .'<ZalogujResult>'.$sid.'</ZalogujResult></ZalogujResponse></s:Body></s:Envelope>';
    }

    private function searchEnvelope(string $innerXml): string
    {
        return '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">'
            .'<s:Body><DaneSzukajPodmiotyResponse xmlns="http://CIS/BIR/PUBL/2014/07">'
            .'<DaneSzukajPodmiotyResult>'.htmlspecialchars($innerXml, ENT_XML1).'</DaneSzukajPodmiotyResult>'
            .'</DaneSzukajPodmiotyResponse></s:Body></s:Envelope>';
    }

    private function fakeGus(string $innerXml): void
    {
        $inner = '<root><dane>'.$innerXml.'</dane></root>';

        Http::fake([
            '*UslugaBIRzewnPubl.svc' => Http::sequence()
                ->push($this->mtom($this->loginEnvelope('SID-TEST-123')), 200)
                ->push($this->mtom($this->searchEnvelope($inner)), 200)
                ->push('', 200), // Wyloguj
        ]);
    }

    public function test_maps_full_name_and_split_address_including_province(): void
    {
        $this->fakeGus(
            '<Regon>240381039</Regon><Nip>1234563218</Nip>'
            .'<Nazwa>RED PAPRIKA RAFAŁ KWAŚNIAK</Nazwa>'
            .'<Wojewodztwo>ŚLĄSKIE</Wojewodztwo><Miejscowosc>Rogoźnik</Miejscowosc>'
            .'<KodPocztowy>42582</KodPocztowy><Ulica>Okrzei</Ulica>'
            .'<NrNieruchomosci>73</NrNieruchomosci><NrLokalu></NrLokalu><Typ>F</Typ>'
        );

        $result = app(GusRegonClient::class)->byNip('1234563218');

        $this->assertSame([
            'company_name' => 'RED PAPRIKA RAFAŁ KWAŚNIAK',
            'street' => 'Okrzei',
            'building_number' => '73',
            'apartment_number' => null,
            'postal_code' => '42-582',
            'city' => 'Rogoźnik',
            'province' => 'śląskie',
        ], $result);
    }

    public function test_coordinator_prefers_gus_over_white_list(): void
    {
        $this->fakeGus(
            '<Nazwa>RED PAPRIKA RAFAŁ KWAŚNIAK</Nazwa>'
            .'<Wojewodztwo>ŚLĄSKIE</Wojewodztwo><Miejscowosc>Rogoźnik</Miejscowosc>'
            .'<KodPocztowy>42582</KodPocztowy><Ulica>Okrzei</Ulica>'
            .'<NrNieruchomosci>73</NrNieruchomosci><NrLokalu></NrLokalu><Typ>F</Typ>'
        );

        $result = app(CompanyLookup::class)->byNip('1234563218');

        $this->assertSame('RED PAPRIKA RAFAŁ KWAŚNIAK', $result['company_name']);
        $this->assertSame('gus', $result['source']);
        $this->assertSame('śląskie', $result['province']);
    }
}
