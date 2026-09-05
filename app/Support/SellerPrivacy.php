<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Shop;

/**
 * Wzór polityki prywatności sklepu — dane i renderowanie.
 *
 * Bliźniak {@see SellerTerms}. Ta sama zasada: TO JEST SZABLON, NIE GENERATOR.
 * Zero AI w czasie działania, te same dane zawsze dają ten sam tekst.
 *
 * DLACZEGO POLITYKA, A NIE TYLKO REGULAMIN: regulamin jest potrzebny od
 * pierwszej sprzedaży, polityka — od pierwszego adresu e-mail, czyli od
 * newslettera i konta klienta, jeszcze zanim ktokolwiek cokolwiek kupi.
 * Do tej pory sklep publikował w jej miejscu zaślepkę, a stało pod nią
 * nazwisko właściciela.
 *
 * PYTAŃ JEST MNIEJ NIŻ W REGULAMINIE — i to nie przypadek. Polityka w ~90%
 * opisuje INFRASTRUKTURĘ (jakie dane płyną, do kogo, na jak długo), a nie
 * biznes sprzedawcy. Wiemy o niej więcej niż on, więc pytamy tylko o to,
 * czego naprawdę nie da się odczytać: kto jest administratorem i jak się
 * z nim skontaktować. Reszta — odbiorcy danych — bierze się z WŁĄCZONYCH
 * integracji sklepu, nie z odpowiedzi w formularzu.
 *
 * STAŁY OBOWIĄZEK, KTÓRY Z TEGO WYNIKA: wzór opisuje naszych podwykonawców.
 * Zmiana operatora płatności albo nowa integracja sprawia, że opublikowane
 * polityki robią się nieaktualne — a stoi pod nimi cudze nazwisko. Stąd
 * numerowanie wersji: bez niego po aktualizacji nie ustalimy, kto ma starą.
 */
class SellerPrivacy
{
    /**
     * Wersja wzoru. PODBIJAĆ przy każdej zmianie treści szablonu — także wtedy,
     * gdy zmienia się tylko lista odbiorców danych. To jest właśnie ta zmiana,
     * po której trzeba wiedzieć, kogo poprosić o ponowne wstawienie.
     */
    public const VERSION = 1;

    /**
     * Pola kreatora. Klucz => czy wymagane.
     *
     * `nip` i `phone` są opcjonalne: działalność nierejestrowana nie ma NIP-u,
     * a telefon nie jest do niczego potrzebny, skoro kanałem kontaktu w sprawach
     * danych jest e-mail. Pustka jest tu poprawną odpowiedzią, nie brakiem.
     */
    public const POLA = [
        'seller_name' => true,
        'nip' => false,
        'address' => true,
        'email' => true,
        'phone' => false,
    ];

    /**
     * Wartości startowe: najpierw to, co już raz wpisano, potem podpowiedzi
     * z profilu sklepu. Pola pokrywają się z regulaminowymi, więc jeżeli
     * sprzedawca wypełnił tamten kreator, ten zastanie komplet.
     *
     * @return array<string, string>
     */
    public static function defaults(Shop $shop, ?Page $page = null): array
    {
        $zapisane = is_array($page?->terms_answers) ? $page->terms_answers : [];

        $zProfilu = [
            'seller_name' => trim((string) $shop->company_name) ?: trim((string) $shop->owner?->name.' '.$shop->owner?->surname),
            'nip' => (string) $shop->nip,
            'address' => (string) $shop->addressLine(),
            'email' => (string) $shop->contact_email,
            'phone' => (string) ($shop->formattedContactPhone() ?? ''),
        ];

        foreach ($zProfilu as $klucz => $wartosc) {
            $zProfilu[$klucz] = (string) ($zapisane[$klucz] ?? $wartosc);
        }

        return $zProfilu;
    }

    /**
     * Treść wzoru — HTML w konwencji podstron (h2/div/ol/ul).
     *
     * Tożsamość bierze się z ODPOWIEDZI, ale lista odbiorców danych ze SKLEPU:
     * tego sprzedawca nie wpisuje ręcznie i nie ma prawa zmyślić. Polityka
     * wymieniająca operatora płatności w sklepie bez płatności online mówiłaby
     * klientowi nieprawdę o jego własnych danych.
     *
     * @param  array<string, string>  $dane
     */
    public static function render(Shop $shop, array $dane): string
    {
        return trim(view('seller.legal.templates.polityka-prywatnosci', [
            'shop' => $shop,
            'dane' => $dane,
        ])->render());
    }

    /**
     * Czy w podstronie stoi jeszcze zaślepka („polityka w przygotowaniu").
     * Jeśli tak — podmieniamy bez ostrzeżenia, bo nie ma czego stracić.
     */
    public static function holdsPlaceholder(?string $content): bool
    {
        $wzorzec = strip_tags((string) config('pages.privacy.content', ''));
        $obecna = strip_tags((string) $content);

        return trim($obecna) === '' || (trim($wzorzec) !== '' && trim($obecna) === trim($wzorzec));
    }
}
