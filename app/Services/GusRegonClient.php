<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Klient GUS REGON (BIR1.1) — pobranie danych firmy po NIP przez SOAP 1.2 z
 * WS-Addressing (czysty HTTP, bez SoapClient — pełna kontrola i testowalność).
 *
 * Zwraca PEŁNĄ nazwę firmy (w tym nazwę handlową JDG) oraz adres ROZBITY na
 * pola wraz z województwem — dokładniejsze niż Biała lista. Wymaga darmowego
 * klucza użytkownika BIR (`services.gus.key`); gdy klucz pusty lub usługa
 * zawiedzie, zwraca null i nadrzędny CompanyLookup spada na Białą listę.
 */
class GusRegonClient
{
    private const NS_ENV = 'http://www.w3.org/2003/05/soap-envelope';
    private const NS_WSA = 'http://www.w3.org/2005/08/addressing';
    private const NS_BIR = 'http://CIS/BIR/PUBL/2014/07';
    private const NS_DATA = 'http://CIS/BIR/PUBL/2014/07/DataContract';
    private const ACTION_BASE = 'http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/';

    public function __construct(private NipService $nip) {}

    /**
     * @return array{company_name:string, street:?string, building_number:?string, apartment_number:?string, postal_code:?string, city:?string, province:?string}|null
     */
    public function byNip(string $nip): ?array
    {
        $nip = $this->nip->normalize($nip) ?? '';

        if (! $this->nip->isValid($nip) || (string) config('services.gus.key') === '') {
            return null;
        }

        try {
            return Cache::remember("gus_regon:{$nip}", now()->addDay(), fn () => $this->fetch($nip));
        } catch (\Throwable) {
            return null; // graceful → fallback do Białej listy
        }
    }

    private function fetch(string $nip): ?array
    {
        $sid = $this->login();
        if ($sid === '') {
            return null;
        }

        try {
            $body = '<ns:DaneSzukajPodmioty><ns:pParametryWyszukiwania>'
                .'<dat:Nip>'.$nip.'</dat:Nip>'
                .'</ns:pParametryWyszukiwania></ns:DaneSzukajPodmioty>';

            $result = $this->extract(
                $this->call('DaneSzukajPodmioty', $body, $sid),
                'DaneSzukajPodmiotyResult',
            );

            return $this->map($result);
        } finally {
            $this->logout($sid);
        }
    }

    private function login(): string
    {
        $body = '<ns:Zaloguj><ns:pKluczUzytkownika>'.config('services.gus.key').'</ns:pKluczUzytkownika></ns:Zaloguj>';

        return trim($this->extract($this->call('Zaloguj', $body), 'ZalogujResult'));
    }

    private function logout(string $sid): void
    {
        try {
            $this->call('Wyloguj', '<ns:Wyloguj><ns:pIdentyfikatorSesji>'.$sid.'</ns:pIdentyfikatorSesji></ns:Wyloguj>', $sid);
        } catch (\Throwable) {
            // wylogowanie best-effort — sesja i tak wygaśnie
        }
    }

    private function call(string $action, string $body, ?string $sid = null): string
    {
        $envelope = '<soap:Envelope xmlns:soap="'.self::NS_ENV.'" xmlns:wsa="'.self::NS_WSA.'" xmlns:ns="'.self::NS_BIR.'" xmlns:dat="'.self::NS_DATA.'">'
            .'<soap:Header>'
            .'<wsa:To>'.$this->endpoint().'</wsa:To>'
            .'<wsa:Action>'.self::ACTION_BASE.$action.'</wsa:Action>'
            .'</soap:Header>'
            .'<soap:Body>'.$body.'</soap:Body>'
            .'</soap:Envelope>';

        // Content-Type MUSI iść przez drugi argument withBody — inaczej Http
        // wysyła domyślnie application/json i GUS odrzuca żądanie (415).
        $contentType = 'application/soap+xml; charset=utf-8; action="'.self::ACTION_BASE.$action.'"';

        $headers = $sid !== null ? ['sid' => $sid] : [];

        $response = Http::withHeaders($headers)->timeout(10)->withBody($envelope, $contentType)->post($this->endpoint());

        if ($response->failed()) {
            throw new RuntimeException('GUS REGON HTTP error: '.$response->status());
        }

        return $response->body();
    }

    /**
     * Wyciąga zawartość elementu wyniku (po local-name, żeby ominąć przestrzenie nazw).
     */
    /**
     * Wyciąga zawartość elementu wyniku bezpośrednio regexem. Pewniejsze niż
     * simplexml na całej odpowiedzi: GUS zwraca MTOM (multipart/related), a
     * koperta SOAP bywa nieparsowalna dla simplexml (przestrzenie WS-Addressing).
     * Treść jest odkodowywana z encji (DaneSzukajPodmiotyResult to zXML-owany
     * string z danymi).
     */
    private function extract(string $soap, string $element): string
    {
        if (preg_match('/<(?:\w+:)?'.$element.'[^>]*>(.*?)<\/(?:\w+:)?'.$element.'>/s', $soap, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return '';
    }

    /**
     * @return array{company_name:string, street:?string, building_number:?string, apartment_number:?string, postal_code:?string, city:?string, province:?string}|null
     */
    private function map(string $innerXml): ?array
    {
        $innerXml = trim($innerXml);
        if ($innerXml === '') {
            return null;
        }

        $data = @simplexml_load_string($innerXml);
        if (! $data instanceof SimpleXMLElement) {
            return null;
        }

        $rows = $data->xpath('//dane');
        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];
        $get = fn (string $field): string => trim((string) ($row->{$field} ?? ''));

        $name = $get('Nazwa');
        if ($name === '' || $get('ErrorCode') !== '') {
            return null;
        }

        return [
            'company_name' => $name,
            'street' => $this->stripStreetPrefix($get('Ulica')) ?: null,
            'building_number' => $get('NrNieruchomosci') ?: null,
            'apartment_number' => $get('NrLokalu') ?: null,
            'postal_code' => $this->formatPostal($get('KodPocztowy')) ?: null,
            'city' => $get('Miejscowosc') ?: null,
            'province' => ($w = $get('Wojewodztwo')) !== '' ? mb_strtolower($w, 'UTF-8') : null,
        ];
    }

    private function stripStreetPrefix(string $street): string
    {
        return trim((string) preg_replace('/^(ul\.?|ulica)\s+/iu', '', trim($street)));
    }

    private function formatPostal(string $code): string
    {
        $digits = preg_replace('/\D/', '', $code);

        return strlen((string) $digits) === 5
            ? substr((string) $digits, 0, 2).'-'.substr((string) $digits, 2)
            : (string) $code;
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.gus.base_url'), '/').'/wsBIR/UslugaBIRzewnPubl.svc';
    }
}
