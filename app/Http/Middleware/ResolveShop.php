<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rozwiązuje najemcę storefrontu z etykiety subdomeny. Grupa `Route::domain`
 * ('{shop}.{central_domain}') wstawia `{shop}` jako parametr trasy = slug sklepu.
 * Nieznany slug → 404 (subdomena bez sklepu nie istnieje). Rozwiązany `Shop` ląduje
 * w atrybutach żądania i jest współdzielony z widokami; bramkę widoczności
 * (szkic → „już wkrótce") trzyma kontroler, bo zależy od oglądającego.
 */
class ResolveShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $shop = Shop::where('slug', $request->route('shop'))->first();

        abort_if($shop === null, 404);

        // Sklep w karencji przed usunięciem gaśnie NATYCHMIAST — także dla
        // właściciela. Dzięki temu w karencji nie wpłynie zamówienie do sklepu,
        // który za kilka dni zniknie razem z historią sprzedaży.
        abort_if($shop->deletion_scheduled_at !== null, 404);

        $request->attributes->set('shop', $shop);
        view()->share('shop', $shop);

        // `{shop}` był tylko selektorem subdomeny — usuwamy go z parametrów trasy,
        // żeby nie wyciekał do argumentów akcji kontrolera.
        $request->route()->forgetParameter('shop');

        return $next($request);
    }
}
