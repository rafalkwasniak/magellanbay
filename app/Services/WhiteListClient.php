<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pobranie danych firmy po NIP z Białej listy podatników VAT (Ministerstwo
 * Finansów) — darmowe REST bez klucza. Zwraca nazwę podatnika i adres rozbity
 * best-effort na pola (adres przychodzi jako jeden string; województwa rejestr
 * nie podaje). Dla JDG `name` to imię i nazwisko, nie nazwa handlowa — stąd GUS
 * jako dokładniejsze źródło nadrzędne (patrz CompanyLookup). Wynik cache'owany
 * per-NIP na 24 h (ochrona limitu 100 zapytań/IP).
 */
class WhiteListClient
{
    public function __construct(private NipService $nip) {}

    /**
     * @return array{company_name:string, street:?string, building_number:?string, apartment_number:?string, postal_code:?string, city:?string}|null
     */
    public function byNip(string $nip): ?array
    {
        $nip = $this->nip->normalize($nip) ?? '';

        if (! $this->nip->isValid($nip)) {
            return null;
        }

        return Cache::remember("whitelist:{$nip}", now()->addDay(), function () use ($nip) {
            $base = rtrim((string) config('services.mf.base_url'), '/');

            $response = Http::acceptJson()->timeout(5)->retry(2, 200)
                ->get("{$base}/api/search/nip/{$nip}", ['date' => now()->toDateString()]);

            if ($response->failed()) {
                return null;
            }

            $subject = $response->json('result.subject');

            if (! is_array($subject) || empty($subject['name'])) {
                return null;
            }

            $address = $subject['residenceAddress'] ?? $subject['workingAddress'] ?? '';

            return array_merge(
                ['company_name' => trim((string) $subject['name'])],
                $this->parseAddress(is_string($address) ? $address : ''),
            );
        });
    }

    /**
     * Best-effort rozbicie jednolinijkowego adresu MF (np. „UL. KWIATOWA 1/2,
     * 00-001 WARSZAWA") na pola. Nierozpoznane fragmenty zostają puste.
     *
     * @return array{street:?string, building_number:?string, apartment_number:?string, postal_code:?string, city:?string}
     */
    private function parseAddress(string $address): array
    {
        $out = [
            'street' => null, 'building_number' => null, 'apartment_number' => null,
            'postal_code' => null, 'city' => null,
        ];

        $address = trim($address);
        if ($address === '') {
            return $out;
        }

        // Kod pocztowy + miejscowość na końcu.
        if (preg_match('/(\d{2}-\d{3})\s+(.+)$/u', $address, $m)) {
            $out['postal_code'] = $m[1];
            $out['city'] = $this->titleCase(trim($m[2]));
            $before = trim((string) mb_substr($address, 0, (int) mb_strpos($address, $m[1])), " ,\t");
        } else {
            $before = $address;
        }

        // Z części przed kodem: ulica + numer (ostatni token z cyfrą).
        $streetPart = preg_replace('/^(ul\.?|ulica)\s+/iu', '', trim($before, " ,\t"));

        if (preg_match('/^(.*?)[\s,]+([0-9][0-9A-Za-z]*(?:\s*\/\s*[0-9A-Za-z]+)?)$/u', (string) $streetPart, $mm)) {
            $out['street'] = $this->titleCase(trim($mm[1]));
            $number = preg_replace('/\s+/', '', $mm[2]);

            if (str_contains((string) $number, '/')) {
                [$building, $apartment] = explode('/', (string) $number, 2);
                $out['building_number'] = $building;
                $out['apartment_number'] = $apartment;
            } else {
                $out['building_number'] = $number;
            }
        } else {
            $out['street'] = $this->titleCase((string) $streetPart);
        }

        return $out;
    }

    /**
     * MF zwraca dane WIELKIMI literami — robimy z tego czytelny Title Case.
     */
    private function titleCase(string $value): string
    {
        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
