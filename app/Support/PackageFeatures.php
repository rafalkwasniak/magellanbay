<?php

namespace App\Support;

/**
 * Lista funkcji pakietu na stronę główną, z zaznaczeniem tego, co DOCHODZI
 * względem pakietu niżej.
 *
 * Po co: karta cennika ma odpowiadać na pytanie „co dostanę, jeśli dopłacę",
 * a nie kazać porównywać trzech kolumn linijka po linijce. Stragan wyróżnia
 * więc to, czego nie ma Kram, a Pawilon — to, czego nie ma Stragan.
 *
 * Porównujemy po KLUCZU cechy, nie po jej opisie: „Do 24 produktów" i „Do 48
 * produktów" to ta sama cecha o innej wartości, więc różnica ma się podświetlić.
 * Tak samo „Przelew i odbiór osobisty" → „Płatności online Paynow".
 *
 * Źródłem prawdy pozostaje `config('shop.packages')` — tutaj tylko tłumaczymy
 * uprawnienia na zdania, które rozumie kupujący.
 */
class PackageFeatures
{
    /**
     * NAJTAŃSZY pakiet zawierający daną funkcję — do zachęt na zablokowanych
     * ekranach („Kody rabatowe w pakiecie Pawilon, 1500 zł/rok").
     *
     * Czytane z configu, a NIE wpisane w treść widoku. Nazwy pakietów wklejone na
     * twardo w Blade były prawdziwe w dniu pisania i zaczęłyby kłamać przy pierwszej
     * zmianie presetów albo nowym pakiecie — a to nieprawda, której nikt nie
     * zauważy, bo brzmi wiarygodnie.
     *
     * @return array{key: string, name: string, price_yearly: int}|null  null, gdy funkcji nie ma nigdzie
     */
    public static function cheapestWith(string $entitlement): ?array
    {
        $found = null;

        foreach (config('shop.packages') as $key => $package) {
            if (($package['entitlements'][$entitlement] ?? false) !== true) {
                continue;
            }

            $price = (int) ($package['price_yearly'] ?? 0);

            if ($found !== null && $price >= $found['price_yearly']) {
                continue;
            }

            $found = ['key' => $key, 'name' => $package['name'], 'price_yearly' => $price];
        }

        return $found;
    }

    /**
     * Pakiety w kolejności z konfiguracji, każdy z listą cech.
     *
     * @return list<array{key: string, name: string, price_yearly: int, features: list<array{label: string, is_new: bool}>}>
     */
    public static function landing(): array
    {
        $packages = [];
        $previous = null;

        foreach (config('shop.packages') as $key => $package) {
            $labels = self::labels($package['entitlements']);
            $features = [];

            foreach ($labels as $feature => $label) {
                $features[] = [
                    'label' => $label,
                    // W najtańszym pakiecie nie ma się do czego porównywać —
                    // wszystko jest „zwykłe", inaczej cała karta byłaby pogrubiona.
                    'is_new' => $previous !== null && ($previous[$feature] ?? null) !== $label,
                ];
            }

            $packages[] = [
                'key' => $key,
                'name' => $package['name'],
                'price_yearly' => (int) $package['price_yearly'],
                'features' => $features,
            ];

            $previous = $labels;
        }

        return $packages;
    }

    /**
     * Siatka „co potrafi Kramio" na stronę główną — JEDNO źródło dla kafelków
     * możliwości. Wcześniej lista siedziała wpisana na twardo w `welcome.blade.php`
     * i po każdym wdrożeniu rozjeżdżała się z rzeczywistością: audyt 2026-08-08
     * wyłapał, że brakowało w niej mapy strony, nadawania paczek InPost i całej
     * warstwy wyglądu sklepu, a płatny Google Analytics nie był wymieniony nigdzie.
     *
     * `requires` to KLUCZ UPRAWNIENIA albo null. Dzięki temu plakietka „od pakietu
     * Stragan" liczy się z `config('shop.packages')`, a nie z pamięci autora
     * tekstu — kafelek nie może obiecać w darmowym pakiecie czegoś, co jest
     * płatne (dokładnie ten błąd miała karta „Wysyłka i odbiór”).
     *
     * @return list<array{icon: string, title: string, description: string, requires: string|null}>
     */
    public static function highlights(): array
    {
        $domain = config('tenancy.central_domain');

        return [
            // Najpierw to, co dostaje KAŻDY — łącznie z darmowym Kramem. Dopiero
            // potem rzeczy z plakietką, żeby czytało się „to masz, a to dochodzi".
            [
                'icon' => '🏪',
                'title' => 'Własny adres i wygląd sklepu',
                'description' => 'Subdomena {nazwa}.'.$domain.' od razu po rejestracji. Osiem szablonów, gotowe palety, własny kolor marki oraz wybór czcionki i zaokrągleń.',
                'requires' => null,
            ],
            [
                'icon' => '⚡',
                'title' => 'Gotowy w kilka minut',
                'description' => 'Rejestracja, dodanie produktu i publikacja — bez wiedzy technicznej. Sklep pokazuje się światu sam, gdy dodasz pierwszy aktywny produkt.',
                'requires' => null,
            ],
            [
                'icon' => '✨',
                'title' => 'Korekta opisów przez AI',
                'description' => 'Napisz szkic — AI poprawi styl, ortografię i interpunkcję jednym kliknięciem. Tygodniowa pula zadań zależy od pakietu.',
                'requires' => null,
            ],
            [
                // SEO: wyłącznie to, co realnie mamy. Danych strukturalnych
                // produktu (schema.org Product) NIE ma — są tylko okruszki —
                // więc nie ma ich też w tym zdaniu.
                'icon' => '🔎',
                'title' => 'SEO i social media',
                'description' => 'Przyjazne adresy, opisy dla wyszukiwarek pisane przez AI, mapa strony i robots.txt Twojego sklepu, weryfikacja w Google Search Console. Wklejony link pokazuje kartę ze zdjęciami produktów.',
                'requires' => null,
            ],
            [
                'icon' => '📄',
                'title' => 'Własne strony informacyjne',
                'description' => 'Dostawa, zwroty, o mnie — dopisujesz podstrony w edytorze i same wchodzą do menu sklepu.',
                'requires' => null,
            ],
            [
                'icon' => '↩',
                'title' => 'Zgodność z prawem',
                'description' => 'Pouczenie o odstąpieniu w mailu, formularz zwrotu dla klienta i rozliczenie w panelu. Do tego najniższa cena z 30 dni przy obniżkach i zgoda na ciasteczka.',
                'requires' => null,
            ],
            [
                'icon' => '👥',
                'title' => 'Klienci pod ręką',
                'description' => 'Konta klientów w Twoim sklepie i kartoteka wszystkich kupujących — razem z tymi, którzy kupili bez zakładania konta.',
                'requires' => null,
            ],
            [
                'icon' => '📊',
                'title' => 'Własna analityka',
                'description' => 'Odwiedziny, sprzedaż i bestsellery liczymy my — nie musisz niczego wklejać ani zakładać kolejnego konta.',
                'requires' => null,
            ],

            // Poniżej: funkcje z bramką pakietu. `requires` steruje plakietką.
            [
                'icon' => '💳',
                'title' => 'Integracja płatności online',
                'description' => 'Podłącz swoje konto Paynow (umowa i weryfikacja po stronie operatora) — klient zapłaci BLIK-iem, kartą lub szybkim przelewem, a pieniądze idą wprost na Twoje konto.',
                'requires' => 'online_payments',
            ],
            [
                'icon' => '📦',
                'title' => 'Wysyłka i nadawanie paczek',
                'description' => 'Paczkomat i kurier w koszyku, a paczkę nadasz i wydrukujesz etykietę wprost z karty zamówienia. Przelew i odbiór osobisty działają w każdym pakiecie.',
                'requires' => 'courier_shipping',
            ],
            [
                'icon' => '🧾',
                'title' => 'Faktury i dane z NIP',
                'description' => 'Faktury przez Fakturownię, a dane firmy pobierzesz po numerze NIP — bez przepisywania z rejestru.',
                'requires' => 'invoices',
            ],
            [
                'icon' => '📈',
                'title' => 'Google Analytics i Tag Manager',
                'description' => 'Wolisz liczyć po swojemu? Wklej własne ID. Baner zgód pilnuje, kiedy skrypt wolno w ogóle uruchomić.',
                'requires' => 'ga_analytics',
            ],
            [
                'icon' => '🎟️',
                'title' => 'Kody rabatowe',
                'description' => 'Procent, kwota albo darmowa dostawa — na cały koszyk lub wybrane produkty, z terminem i limitem użyć.',
                'requires' => 'discount_codes',
            ],
            [
                'icon' => '📣',
                'title' => 'Wiadomości do klientów',
                'description' => 'Napisz o nowościach do klientów, którzy się zgodzili — z kartą produktu w mailu i linkiem wypisu.',
                'requires' => 'bulk_mail',
            ],
            [
                'icon' => '✏️',
                'title' => 'Edycja zamówień',
                'description' => 'Popraw ilość, dołóż pozycję albo skoryguj adres już po złożeniu zamówienia.',
                'requires' => 'order_editing',
            ],
        ];
    }

    /**
     * Funkcje KONKRETNEGO SKLEPU — czytane z jego uprawnień, nie z presetu
     * pakietu. Różnica jest istotna: sklep może mieć moduł nadany ręcznie poza
     * pakietem, a wtedy lista z configu kłamałaby.
     *
     * Domyślnie bierzemy stan EFEKTYWNY (`entitlement`), więc po wygaśnięciu
     * abonamentu sprzedawca widzi to, co faktycznie działa, a nie to, co kiedyś
     * kupił. `raw: true` czyta snapshot — do jednego pytania odwrotnego: „co
     * wróci po opłacie" (mail o wygaśnięciu), gdzie stan efektywny mówiłby o
     * pakiecie darmowym, czyli o niczym.
     *
     * @return list<string>
     */
    public static function forShop(\App\Models\Shop $shop, bool $raw = false): array
    {
        $keys = ['max_products', 'ai_weekly_limit', 'online_payments', 'courier_shipping', 'invoices', 'ga_analytics', 'order_editing', 'discount_codes', 'bulk_mail'];

        $entitlements = array_combine(
            $keys,
            array_map(
                fn (string $key): mixed => $raw ? $shop->rawEntitlement($key) : $shop->entitlement($key),
                $keys,
            ),
        );

        return array_values(self::labels($entitlements));
    }

    /**
     * Uprawnienia pakietu jako zdania dla kupującego. Klucz tablicy identyfikuje
     * CECHĘ (po nim porównujemy pakiety), wartość to jej opis.
     *
     * @param  array<string, mixed>  $entitlements
     * @return array<string, string>
     */
    private static function labels(array $entitlements): array
    {
        return array_filter([
            'max_products' => 'Do '.$entitlements['max_products'].' produktów',
            'storefront' => 'Własny adres, szablony i kolory sklepu',
            // Tygodniowa pula zadań AI RÓŻNI SIĘ między pakietami (100/400/800),
            // a wcześniejsza etykieta „Opisy z korektą AI" była dla wszystkich
            // identyczna — realne ograniczenie i realna różnica znikały z cennika.
            // Liczba w etykiecie sprawia, że wyższy pakiet podświetla ją sam.
            'ai' => 'Korekta AI: '.$entitlements['ai_weekly_limit'].' zadań tygodniowo',
            // Zwroty są w KAŻDYM pakiecie — prawo odstąpienia przysługuje
            // konsumentowi niezależnie od tego, ile sprzedawca nam płaci.
            'returns' => 'Zwroty 14 dni zgodne z prawem',
            // „Integracja z Paynow", nie „Płatności online Paynow" — pakiet daje
            // podłączenie, a nie usługę płatniczą: umowę z operatorem sprzedawca
            // zawiera sam i wkleja własne klucze (patrz Integracje).
            'payments' => $entitlements['online_payments'] ? 'Integracja płatności online (własne konto Paynow)' : 'Przelew i odbiór osobisty',
            'shipping' => $entitlements['courier_shipping'] ? 'Paczkomat, kurier i nadawanie paczek InPost' : null,
            'invoices' => $entitlements['invoices'] ? 'Integracja z Fakturownią' : null,
            // Google Analytics jest płatny i egzekwowany (`Shop::tracksWith…`),
            // a nie był wymieniony w ŻADNEJ karcie — kupujący nie miał jak się
            // dowiedzieć, że w ogóle to sprzedajemy.
            'analytics' => $entitlements['ga_analytics'] ? 'Google Analytics i Tag Manager' : null,
            'order_editing' => $entitlements['order_editing'] ? 'Edycja zamówień' : null,
            'discount_codes' => $entitlements['discount_codes'] ? 'Kody rabatowe w koszyku' : null,
            'bulk_mail' => $entitlements['bulk_mail'] ? 'Wiadomości do klientów' : null,
        ]);
    }
}
