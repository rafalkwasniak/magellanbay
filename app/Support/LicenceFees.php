<?php

namespace App\Support;

/**
 * Reguła naliczania opłat licencyjnych.
 *
 * ===========================================================================
 * SUMA PO LICENCJODAWCACH, MAKSIMUM WEWNĄTRZ JEDNEGO
 * ===========================================================================
 *
 * Grupujemy opłaty po firmie, w każdej grupie bierzemy NAJWYŻSZĄ, a wyniki
 * SUMUJEMY (ustalenie z klientem, potwierdzone 05.09.2026):
 *
 *   Bieg Gdański 5 zł + Bieg Gdański 8 zł              → 8 zł
 *   Bieg Gdański 5 zł + PZLA 7 zł                      → 12 zł
 *   Bieg Gdański 5 zł + Bieg Gdański 8 zł + PZLA 7 zł  → 15 zł
 *
 * SENS BIZNESOWY, dla którego to nie jest kaprys: umowa licencyjna dotyczy
 * PRAWA DO ZNAKU jednej firmy. Użycie go dwa razy na jednym magnesie nie jest
 * dwoma użyciami prawa, tylko jednym — więc partner nie inkasuje dwa razy.
 * Dwie różne firmy to dwa różne prawa i każda należność stoi osobno.
 *
 * DLACZEGO OSOBNA KLASA, A NIE PĘTLA W KALKULATORZE CENY. Bo tę samą regułę
 * stosuje się w trzech miejscach: przy liczeniu ceny w koszyku, przy zapisie
 * rozbicia na zamówieniu i przy rozliczeniu z partnerem. Wpisana trzy razy,
 * rozjechałaby się przy pierwszej korekcie — a rozjazd między tym, co klient
 * zapłacił, a tym, co wypłacamy partnerowi, jest dokładnie tym błędem, którego
 * nikt nie zauważy przez kwartał.
 *
 * OPŁATA BEZ PARTNERA (`licensor_id === null`) NIE PODLEGA REGULE i sumuje się
 * zwyczajnie. Grupowanie po `null` sklejałoby w jedną kupkę opłaty należne
 * różnym, nieprzypisanym jeszcze podmiotom i po cichu obcinało kwotę.
 */
final class LicenceFees
{
    /**
     * Redukuje listę opłat do tych, które faktycznie się należą.
     *
     * Zwraca PODZBIÓR wejścia — te same wiersze, bez odrzuconych. Nie sumuje ich
     * w jedną kwotę, bo wołający potrzebuje wiedzieć, KTÓRA opłata przeszła:
     * na rozbiciu ceny i w rozliczeniu musi stać etykieta zwycięskiej pozycji
     * („Kotwica — Bieg Gdański"), a nie sama liczba.
     *
     * Przy remisie wygrywa PIERWSZA z listy — kolejność jest deterministyczna
     * (grupy i pozycje sortowane po `position`), więc ten sam koszyk zawsze da
     * ten sam wynik. Losowanie zwycięzcy sprawiłoby, że dwa identyczne
     * zamówienia miałyby różne rozbicia.
     *
     * @param  list<array{licensor_id: int|null, amount: float, ...}>  $fees
     * @return list<array{licensor_id: int|null, amount: float, ...}>
     */
    public static function reduce(array $fees): array
    {
        $best = [];      // licensor_id => indeks zwycięskiej opłaty
        $standalone = []; // opłaty bez przypisanego partnera — wszystkie zostają

        foreach ($fees as $index => $fee) {
            $licensorId = $fee['licensor_id'] ?? null;
            $amount = (float) ($fee['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            if ($licensorId === null) {
                $standalone[] = $index;

                continue;
            }

            $current = $best[$licensorId] ?? null;

            if ($current === null || $amount > (float) $fees[$current]['amount']) {
                $best[$licensorId] = $index;
            }
        }

        $keep = array_merge($standalone, array_values($best));
        sort($keep);

        return array_values(array_map(fn (int $i) => $fees[$i], $keep));
    }

    /**
     * Łączna kwota należnych opłat — wygodny skrót nad `reduce()`.
     *
     * @param  list<array{licensor_id: int|null, amount: float, ...}>  $fees
     */
    public static function total(array $fees): float
    {
        $sum = 0.0;

        foreach (self::reduce($fees) as $fee) {
            $sum += (float) $fee['amount'];
        }

        return round($sum, 2);
    }
}
