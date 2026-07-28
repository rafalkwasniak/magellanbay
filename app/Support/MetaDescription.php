<?php

namespace App\Support;

/**
 * Opis SEO wpisany RĘCZNIE przez sprzedawcę — jedno miejsce dla produktu i
 * sklepu, żeby reguła własności tekstu nie rozjechała się między dwoma
 * formularzami.
 *
 * Reguła: tekst wpisany ręcznie należy do sprzedawcy i automat (docelowo AI)
 * nigdy go nie nadpisze. Wyczyszczenie pola oddaje kontrolę automatowi —
 * najprostsze możliwe „cofnij", bez dodatkowego przycisku i bez tłumaczenia
 * użytkownikowi, czym jest „tryb automatyczny".
 */
class MetaDescription
{
    /**
     * Pola do zapisu na modelu: sam opis i znacznik własności.
     *
     * @return array{meta_description: ?string, meta_description_manual: bool}
     */
    public static function fields(?string $value): array
    {
        $value = trim((string) $value);

        return [
            'meta_description' => $value !== '' ? $value : null,
            'meta_description_manual' => $value !== '',
        ];
    }
}
