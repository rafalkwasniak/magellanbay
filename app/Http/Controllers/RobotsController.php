<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `robots.txt` składany per HOST — to on sprawia, że mapa strony działa BEZ
 * jakiegokolwiek udziału sprzedawcy. Robot pobiera ten plik przy pierwszej
 * wizycie na danym hoście, znajduje w nim linię `Sitemap:` i idzie po mapę.
 * Nikt niczego nie zgłasza, nie zakłada konta i nie klika.
 *
 * Każdy host wskazuje WYŁĄCZNIE swoją mapę: `lemoniady.kramio.pl/robots.txt`
 * kieruje na `lemoniady.kramio.pl/sitemap.xml`, centrala na swoją. Nie ma jednego
 * pliku z listą wszystkich map — byłby publicznym spisem sklepów na platformie.
 *
 * UWAGA na przyszłość: to działa tylko dlatego, że `public/robots.txt` NIE
 * ISTNIEJE. `.htaccess` oddaje istniejące pliki bezpośrednio, z pominięciem
 * Laravela, więc odtworzenie tam statycznego pliku ucisza tę trasę bez żadnego
 * widocznego objawu poza tym, że „przestało działać".
 */
class RobotsController extends Controller
{
    /**
     * Centrala. Panele admina i sprzedawcy odcinamy jawnie: siedzą i tak za
     * logowaniem, ale ich adresy niosą numery zamówień i identyfikatory klientów,
     * więc nie ma po co zapraszać do nich robotów.
     */
    public function central(): Response
    {
        return $this->text([
            'User-agent: *',
            'Disallow: /panel',
            'Disallow: /administrator',
            'Disallow: /sprzedawca',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ]);
    }

    /**
     * Storefront. Odcinamy ścieżki transakcyjne i konto klienta — mają już
     * `noindex`, ale `noindex` działa dopiero PO wejściu robota na stronę,
     * a tutaj oszczędzamy mu wizyty. Sklep w szkicu nie dostaje wskazania mapy,
     * bo mapy nie ma (patrz SitemapController::storefront).
     */
    public function storefront(Request $request): Response
    {
        $shop = $request->attributes->get('shop');

        $lines = [
            'User-agent: *',
            'Disallow: /koszyk',
            'Disallow: /kasa',
            'Disallow: /platnosc',
            'Disallow: /moje-konto',
            'Disallow: /zwrot',
            'Disallow: /wypisz-sie',
        ];

        if ($shop->isVisible()) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.SitemapController::urlFor($shop);
        }

        return $this->text($lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private function text(array $lines): Response
    {
        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
