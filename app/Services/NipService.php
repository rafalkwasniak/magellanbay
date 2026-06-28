<?php

namespace App\Services;

/**
 * Normalizacja i walidacja polskiego NIP. Normalizacja sprowadza wejście do
 * samych cyfr (znosi spacje, myślniki, prefiks „PL"); walidacja sprawdza sumę
 * kontrolną mod-11. Wzorzec jak PhoneService — jedno źródło prawdy, wpinane do
 * Form Requestów.
 */
class NipService
{
    /** Wagi cyfr 1–9 w sumie kontrolnej NIP. */
    private const WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    /**
     * Do samych cyfr; pusty wynik → null (pole opcjonalne).
     */
    public function normalize(?string $nip): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $nip);

        return $digits === '' ? null : $digits;
    }

    /**
     * Poprawność NIP: 10 cyfr + zgodna suma kontrolna mod-11.
     */
    public function isValid(string $nip): bool
    {
        if (preg_match('/^\d{10}$/', $nip) !== 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += self::WEIGHTS[$i] * (int) $nip[$i];
        }

        $checksum = $sum % 11;

        // Reszta 10 nie jest dopuszczalna jako cyfra kontrolna.
        return $checksum !== 10 && $checksum === (int) $nip[9];
    }
}
