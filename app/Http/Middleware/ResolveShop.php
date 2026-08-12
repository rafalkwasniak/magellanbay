<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\SubdomainAvailability;
use App\Support\Central;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rozwiązuje najemcę storefrontu z etykiety subdomeny. Grupa `Route::domain`
 * ('{shop}.{central_domain}') wstawia `{shop}` jako parametr trasy = slug sklepu.
 * Rozwiązany `Shop` ląduje w atrybutach żądania i jest współdzielony z widokami;
 * bramkę widoczności (szkic → „już wkrótce") trzyma kontroler, bo zależy od
 * oglądającego.
 *
 * Subdomena bez sklepu NIE jest ślepym 404: pokazujemy stronę „ten adres jest
 * wolny — zajmij go" (albo jej wersję dla adresów zajętych). Status zostaje
 * 404, bo pod wildcardem istnieje nieskończenie wiele takich adresów i żaden
 * nie ma prawa trafić do indeksu Google.
 */
class ResolveShop
{
    public function __construct(private readonly SubdomainAvailability $availability) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('shop');

        // `www` to druga nazwa centrali, nie sklep. Bez tego wpada we wzorzec
        // {shop}.{central_domain} i kończy 404 — a to adres, który ludzie
        // wpisują z palca i podają sobie dalej.
        if ($slug === 'www') {
            return redirect()->to(
                Central::url($request->getRequestUri()),
                $request->isMethodSafe() ? 301 : 308
            );
        }

        $shop = Shop::where('slug', $slug)->first();

        // Sklep w karencji przed usunięciem gaśnie NATYCHMIAST — także dla
        // właściciela. Dzięki temu w karencji nie wpłynie zamówienie do sklepu,
        // który za kilka dni zniknie razem z historią sprzedaży. Adres wolny
        // wtedy NIE jest: za chwilę wejdzie w kwarantannę.
        if ($shop === null || $shop->deletion_scheduled_at !== null) {
            return $this->withoutShop(
                $request,
                $slug,
                free: $shop === null && $this->availability->isFree($slug),
            );
        }

        $request->attributes->set('shop', $shop);
        view()->share('shop', $shop);

        // `{shop}` był tylko selektorem subdomeny — usuwamy go z parametrów trasy,
        // żeby nie wyciekał do argumentów akcji kontrolera.
        $request->route()->forgetParameter('shop');

        return $next($request);
    }

    /**
     * Odpowiedź dla subdomeny, pod którą nie stoi żaden czynny sklep.
     */
    private function withoutShop(Request $request, string $slug, bool $free): Response
    {
        // Ładną stronę dostają tylko żądania o stronę. `robots.txt` i
        // `sitemap.xml` zostają przy gołym 404 — plik tekstowy z HTML-em
        // w środku jest gorszy niż brak pliku.
        if (! $request->isMethod('GET') || pathinfo($request->path(), PATHINFO_EXTENSION) !== '') {
            abort(404);
        }

        return response()
            ->view('platform.unclaimed-subdomain', [
                'slug' => $slug,
                'host' => $slug.'.'.config('tenancy.central_domain'),
                'free' => $free,
            ], 404)
            ->header('X-Robots-Tag', 'noindex');
    }
}
