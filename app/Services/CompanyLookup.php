<?php

namespace App\Services;

/**
 * Koordynator pobierania danych firmy po NIP. Najpierw próbuje GUS REGON
 * (dokładne: pełna nazwa handlowa + adres rozbity na pola wraz z województwem);
 * gdy GUS jest niedostępny lub niezaskonfigurowany (brak klucza), spada na
 * Białą listę MF (działa zawsze, ale dla JDG zwraca imię i nazwisko bez
 * województwa). Dzięki temu funkcja działa od razu, a po wgraniu klucza GUS
 * dane stają się dokładniejsze — bez zmian w kontrolerze/froncie.
 */
class CompanyLookup
{
    public function __construct(
        private GusRegonClient $gus,
        private WhiteListClient $whiteList,
    ) {}

    /**
     * @return array{company_name:string, street:?string, building_number:?string, apartment_number:?string, postal_code:?string, city:?string, province?:?string, source?:string}|null
     */
    public function byNip(string $nip): ?array
    {
        $fromGus = $this->gus->byNip($nip);

        if ($fromGus !== null && ($fromGus['company_name'] ?? '') !== '') {
            return $fromGus + ['source' => 'gus'];
        }

        $fromWhiteList = $this->whiteList->byNip($nip);

        return $fromWhiteList !== null
            ? $fromWhiteList + ['source' => 'whitelist']
            : null;
    }
}
