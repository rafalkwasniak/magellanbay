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
     * Funkcje KONKRETNEGO SKLEPU — czytane z jego uprawnień, nie z presetu
     * pakietu. Różnica jest istotna: sklep może mieć moduł nadany ręcznie poza
     * pakietem, a wtedy lista z configu kłamałaby.
     *
     * Bierzemy stan EFEKTYWNY (`entitlement`), więc po wygaśnięciu abonamentu
     * sprzedawca widzi to, co faktycznie działa, a nie to, co kiedyś kupił.
     *
     * @return list<string>
     */
    public static function forShop(\App\Models\Shop $shop): array
    {
        $keys = ['max_products', 'online_payments', 'courier_shipping', 'invoices', 'order_editing', 'discount_codes', 'bulk_mail'];

        $entitlements = array_combine(
            $keys,
            array_map(fn (string $key): mixed => $shop->entitlement($key), $keys),
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
            'storefront' => 'Własny adres i strona sklepu',
            'ai' => 'Opisy z korektą AI',
            // Zwroty są w KAŻDYM pakiecie — prawo odstąpienia przysługuje
            // konsumentowi niezależnie od tego, ile sprzedawca nam płaci.
            'returns' => 'Zwroty 14 dni zgodne z prawem',
            // „Integracja z Paynow", nie „Płatności online Paynow" — pakiet daje
            // podłączenie, a nie usługę płatniczą: umowę z operatorem sprzedawca
            // zawiera sam i wkleja własne klucze (patrz Integracje).
            'payments' => $entitlements['online_payments'] ? 'Integracja płatności online (własne konto Paynow)' : 'Przelew i odbiór osobisty',
            'shipping' => $entitlements['courier_shipping'] ? 'Wysyłka kurierem i przez InPost' : null,
            'invoices' => $entitlements['invoices'] ? 'Integracja z Fakturownią' : null,
            'order_editing' => $entitlements['order_editing'] ? 'Edycja zamówień' : null,
            'discount_codes' => $entitlements['discount_codes'] ? 'Kody rabatowe w koszyku' : null,
            'bulk_mail' => $entitlements['bulk_mail'] ? 'Wiadomości do klientów' : null,
        ]);
    }
}
