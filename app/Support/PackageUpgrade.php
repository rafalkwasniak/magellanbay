<?php

namespace App\Support;

use App\Models\Shop;
use Carbon\CarbonInterface;

/**
 * Wycena zmiany pakietu w trakcie opłaconego okresu.
 *
 * ZASADA (decyzja Rafała 2026-07-29): zmiana pakietu to ZAKUP PEŁNEGO ROKU
 * nowego pakietu, a niewykorzystana część obecnego wchodzi jako ZNIŻKA.
 *
 *     zniżka        = cena obecnego × dni do końca ÷ 365
 *     do zapłaty    = cena nowego (rok) − zniżka
 *     nowy termin   = dziś + rok
 *
 * Dlaczego tak, a nie „dopłata do starego terminu": klient, który po pół roku
 * przechodzi wyżej, zostaje z nami na KOLEJNY ROK, nie na pozostałe sześć
 * miesięcy. Nic nie traci — resztówka wraca mu w zniżce — a my zyskujemy
 * pełny okres retencji.
 *
 * Zaokrąglenie kwoty do zapłaty W DÓŁ do pełnych złotych: prostsza kwota
 * w rozmowie i zawsze na korzyść klienta.
 *
 * TRZY PRZYPADKI:
 *  - z pakietu DARMOWEGO → pełna cena roku (darmowy nie ma czego kredytować),
 *  - z płatnego na DROŻSZY → rok minus zniżka za resztówkę, nowy termin,
 *  - z płatnego na TAŃSZY → nic do zapłaty i żadnego zwrotu; obniżka wchodzi
 *    przy odnowieniu, a do tego czasu działa to, co klient opłacił.
 *
 * Zniżkę liczymy z CENY TEGO SKLEPU (`priceYearly()`), nie z cennika — sklep
 * może mieć cenę indywidualną i to ona jest podstawą rozliczenia.
 */
class PackageUpgrade
{
    /** Umowna długość roku abonamentowego. Nie mamy daty startu, więc proporcję liczymy od niej. */
    private const YEAR_DAYS = 365;

    /**
     * Wycena przejścia sklepu na wskazany pakiet.
     *
     * @return array{kind: string, amount: float, credit: float, full_price: float, days_left: int, new_ends_at: CarbonInterface|null}
     *         kind: 'full' (pełny rok bez zniżki) | 'credit' (rok minus resztówka)
     *               | 'downgrade' (nic teraz) | 'unavailable'
     */
    public static function quote(Shop $shop, string $targetPackage): array
    {
        $target = config("shop.packages.{$targetPackage}");

        if ($target === null) {
            return self::result('unavailable', 0.0, 0.0, 0.0, 0, null);
        }

        $targetPrice = (float) ($target['price_yearly'] ?? 0);
        $currentPrice = $shop->priceYearly();
        $endsAt = $shop->subscription_ends_at;
        $newEndsAt = now()->copy()->addYear()->endOfDay();

        // Tańszy pakiet: klient dogrywa obecny do końca terminu, nic nie płaci.
        if ($targetPrice <= $currentPrice) {
            return self::result('downgrade', 0.0, 0.0, $targetPrice, 0, null);
        }

        // Z darmowego, bez terminu, po wygaśnięciu albo przy dostępie gratisowym
        // nie ma resztówki do skredytowania — pełny rok.
        $daysLeft = $endsAt !== null ? self::daysLeft($endsAt) : 0;

        if ($currentPrice <= 0 || $shop->comped || $daysLeft <= 0) {
            return self::result('full', $targetPrice, 0.0, $targetPrice, 0, $newEndsAt);
        }

        $credit = $currentPrice * $daysLeft / self::YEAR_DAYS;

        return self::result(
            'credit',
            // Zaokrąglenie w dół dotyczy KWOTY DO ZAPŁATY — zawsze na korzyść klienta.
            max(0.0, (float) floor($targetPrice - $credit)),
            round($credit, 2),
            $targetPrice,
            $daysLeft,
            $newEndsAt,
        );
    }

    /**
     * Wyceny wszystkich pakietów DROŻSZYCH od obecnego, w kolejności z cennika.
     *
     * @return array<string, array{kind: string, amount: float, credit: float, full_price: float, days_left: int, new_ends_at: CarbonInterface|null}>
     */
    public static function upgradeQuotes(Shop $shop): array
    {
        $quotes = [];

        foreach (config('shop.packages') as $slug => $package) {
            if ((float) ($package['price_yearly'] ?? 0) > $shop->priceYearly()) {
                $quotes[$slug] = self::quote($shop, $slug);
            }
        }

        return $quotes;
    }

    /**
     * @return array{kind: string, amount: float, credit: float, full_price: float, days_left: int, new_ends_at: CarbonInterface|null}
     */
    private static function result(string $kind, float $amount, float $credit, float $fullPrice, int $daysLeft, ?CarbonInterface $newEndsAt): array
    {
        return [
            'kind' => $kind,
            'amount' => $amount,
            'credit' => $credit,
            'full_price' => $fullPrice,
            'days_left' => $daysLeft,
            'new_ends_at' => $newEndsAt,
        ];
    }

    /**
     * Pełne dni do końca abonamentu, licząc od początku dziś — ostatni dzień
     * abonamentu jest jeszcze opłacony, więc liczy się na korzyść sklepu.
     */
    private static function daysLeft(CarbonInterface $endsAt): int
    {
        return max(0, (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false));
    }
}
