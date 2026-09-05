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
     *                                                                                                                                 kind: 'full' (pełny rok bez zniżki) | 'credit' (rok minus resztówka)
     *                                                                                                                                 | 'renewal' (ten sam pakiet — rok doklejony do terminu)
     *                                                                                                                                 | 'downsize' (tańszy pakiet w oknie odnowienia)
     *                                                                                                                                 | 'downgrade' (tańszy, ale za wcześnie) | 'unavailable'
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

        // TEN SAM pakiet to nie zmiana, a przedłużenie — inna reguła terminu,
        // więc odbijamy do renewal(), zamiast wpaść niżej w „tańszy pakiet".
        if ($targetPackage === $shop->package) {
            return self::renewal($shop);
        }

        // Tańszy pakiet: kupuje się go tylko W OKNIE ODNOWIENIA, patrz downsize().
        if ($targetPrice <= $currentPrice) {
            return self::downsize($shop, $targetPackage);
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
     * Wycena PRZEDŁUŻENIA obecnego pakietu o rok.
     *
     * Inna reguła terminu niż przy zmianie pakietu — i celowo. Przy zmianie rok
     * liczy się od dnia opłacenia, a resztówka wraca jako zniżka, bo klient
     * bierze inny produkt. Przy przedłużeniu bierze TEN SAM produkt, więc rok
     * DOKLEJA SIĘ do starego terminu (decyzja Rafała): kto płaci 30 dni przed
     * końcem, ma opłacone przez 13 miesięcy i nie musi czekać z przelewem do
     * ostatniego dnia; kto płaci 3 dni po terminie, dostaje rok liczony od tego
     * terminu, czyli o te 3 dni krótszy — spóźnienie nie jest premiowane.
     *
     * Granicą jest CIĄGŁOŚĆ abonamentu (`subscriptionActive()`, więc też
     * karencja). Gdy pakiet przepadł na dobre, rok leci od dziś: doklejanie do
     * daty sprzed pół roku sprzedawałoby pół roku dostępu za cenę roku.
     *
     * Skutek uboczny do wiedzy: na styku karencji opłata dzień później daje
     * kilka dni więcej. Świadomie — alternatywą było premiowanie zwłoki.
     *
     * Cena to `priceYearly()` TEGO SKLEPU, nie cennik: sklep z ceną
     * indywidualną przedłuża na swoich warunkach.
     *
     * @return array{kind: string, amount: float, credit: float, full_price: float, days_left: int, new_ends_at: CarbonInterface|null}
     *                                                                                                                                 kind: 'renewal' albo 'unavailable' (darmowy i gratisowy nie mają czego przedłużać)
     */
    public static function renewal(Shop $shop): array
    {
        $price = $shop->priceYearly();

        if ($price <= 0 || $shop->comped) {
            return self::result('unavailable', 0.0, 0.0, 0.0, 0, null);
        }

        $endsAt = $shop->subscription_ends_at;
        $base = ($endsAt !== null && $shop->subscriptionActive()) ? $endsAt->copy() : now()->copy();

        return self::result(
            'renewal',
            $price,
            0.0,
            $price,
            $endsAt !== null ? max(0, self::daysLeft($endsAt)) : 0,
            $base->addYear()->endOfDay(),
        );
    }

    /**
     * Zniżka DO POKAZANIA: różnica między ceną roku a kwotą do zapłaty, a nie
     * surowa proporcja z `credit`.
     *
     * Kwotę do zapłaty zaokrąglamy w dół do złotówek, więc surowa zniżka nigdy nie
     * domyka rachunku na ekranie: przy 750 zł i zniżce 16,44 wychodzi 733, a nie
     * 733,56. Sprzedawca ma móc dodać dwie liczby w głowie i trafić w trzecią,
     * dlatego pokazujemy 17 zł. Wyliczenie z RÓŻNICY, nie osobne zaokrąglenie w
     * górę — inaczej przy niektórych cenach rozjechałoby się o grosz.
     */
    public static function discountShown(array $quote): float
    {
        return $quote['amount'] > 0
            ? round((float) $quote['full_price'] - (float) $quote['amount'], 2)
            : 0.0;
    }

    /**
     * Wycena ZEJŚCIA na tańszy pakiet — możliwa tylko w OKNIE ODNOWIENIA, czyli
     * gdy do końca terminu zostało nie więcej niż `notice_days` (30) dni, albo
     * abonament już minął.
     *
     * DLACZEGO OKNO (warunek Rafała): bez niego zejście dzień po zakupie dawałoby
     * zniżkę niemal równą całej wpłacie — kto kupiłby Pawilon za 1500, nazajutrz
     * „zszedłby" na Stragan i wyszłoby 750 − 1496 = −746 zł, czyli żądanie zwrotu
     * gotówki. W oknie 30 dni zniżka nie przekroczy 1500 × 30/365 = 123,29 zł, więc
     * kwota do zapłaty zostaje dodatnia, a arbitraż jest niemożliwy: kolejne okno
     * otwiera się dopiero 11 miesięcy po zakupie.
     *
     * TWARDA BLOKADA obok okna: gdyby zniżka kiedykolwiek dorównała kwocie do
     * zapłaty, zejścia po prostu nie oferujemy. W obecnym cenniku to niemożliwe
     * (trzeba by pakietu 12× droższego od docelowego), ale cena indywidualna
     * ustawiona z konsoli nie zna cennika — a zwrot gotówki nie ma się wydarzyć
     * nigdy, nie tylko „przy typowych cenach".
     *
     * Termin liczy się OD DZIŚ, nie od starego: skoro resztówka wraca jako zniżka,
     * to się zużywa. Tak samo jak przy przejściu wyżej — jedna zasada na oba
     * kierunki, w odróżnieniu od przedłużenia w tym samym pakiecie, gdzie nic nie
     * oddajesz i rok dokleja się do terminu.
     *
     * @return array{kind: string, amount: float, credit: float, full_price: float, days_left: int, new_ends_at: CarbonInterface|null}
     *                                                                                                                                 kind: 'downsize' (do kupienia teraz) | 'downgrade' (za wcześnie — poza oknem)
     *                                                                                                                                 | 'unavailable' (darmowy cel albo zniżka zjadłaby całą kwotę)
     */
    public static function downsize(Shop $shop, string $targetPackage): array
    {
        $targetPrice = (float) config("shop.packages.{$targetPackage}.price_yearly", 0);

        // Kramu nie da się „kupić" za zero złotych — zejście na darmowy dzieje się
        // samo: nie odnawiasz, pakiet wygasa, sklep czyta się jako Kram.
        if ($targetPrice <= 0) {
            return self::result('unavailable', 0.0, 0.0, 0.0, 0, null);
        }

        $endsAt = $shop->subscription_ends_at;
        $daysLeft = $endsAt !== null ? self::daysLeft($endsAt) : 0;
        $window = (int) config('shop.subscription.notice_days');

        if ($daysLeft > $window) {
            return self::result('downgrade', 0.0, 0.0, $targetPrice, $daysLeft, null);
        }

        $credit = ($shop->comped || $shop->priceYearly() <= 0)
            ? 0.0
            : $shop->priceYearly() * $daysLeft / self::YEAR_DAYS;

        $amount = (float) floor($targetPrice - $credit);

        if ($amount <= 0) {
            return self::result('unavailable', 0.0, round($credit, 2), $targetPrice, $daysLeft, null);
        }

        return self::result(
            'downsize',
            $amount,
            round($credit, 2),
            $targetPrice,
            $daysLeft,
            now()->copy()->addYear()->endOfDay(),
        );
    }

    /**
     * Wyceny pakietów TAŃSZYCH od obecnego — te, które da się kupić teraz.
     * Poza oknem odnowienia zwraca pustą tablicę, więc ekran sam się wycisza.
     *
     * @return array<string, array{kind: string, amount: float, credit: float, full_price: float, days_left: int, new_ends_at: CarbonInterface|null}>
     */
    public static function downsizeQuotes(Shop $shop): array
    {
        $quotes = [];

        // Tylko pakiety z oferty — presety spoza cennika (np. „Sklep dedykowany")
        // mają cenę 0 i wpadłyby tu jako najtańsze zejście dla każdego sklepu.
        foreach (PackageFeatures::purchasable() as $slug => $package) {
            if ((float) ($package['price_yearly'] ?? 0) >= $shop->priceYearly()) {
                continue;
            }

            $quote = self::downsize($shop, $slug);

            if ($quote['kind'] === 'downsize') {
                $quotes[$slug] = $quote;
            }
        }

        return $quotes;
    }

    /**
     * Wyceny wszystkich pakietów DROŻSZYCH od obecnego, w kolejności z cennika.
     *
     * @return array<string, array{kind: string, amount: float, credit: float, full_price: float, days_left: int, new_ends_at: CarbonInterface|null}>
     */
    public static function upgradeQuotes(Shop $shop): array
    {
        $quotes = [];

        foreach (PackageFeatures::purchasable() as $slug => $package) {
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
