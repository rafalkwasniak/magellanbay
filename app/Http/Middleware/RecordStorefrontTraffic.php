<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\TrafficRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zlicza ruch storefrontu do dziennego agregatu (Poziom 2). Biegnie PO `ResolveShop`
 * (który wstawia sklep w atrybuty żądania). Zasady „nie zafałszować i nie obciążać":
 * tylko GET, boty odsiane po user-agencie, właściciel/administrator podglądający
 * własny sklep NIE liczy się jako klient, a wizytę liczymy raz na sesję/dzień
 * (kolejne podstrony to nie nowe wizyty). Zapis po wyrenderowaniu strony i w
 * try/catch — problem z licznikiem nie może wywrócić storefrontu.
 */
class RecordStorefrontTraffic
{
    /** Wystarczająco szeroki wzorzec na crawlery/podglądy linków — bez pretensji do kompletności. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|bing|yahoo|duckduck|baidu|yandex|facebookexternalhit|embedly|quora|pinterest|slackbot|telegram|whatsapp|discord|preview|monitor|curl|wget|python-requests|axios|headless|lighthouse|pingdom|uptime/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request);
        } catch (\Throwable) {
            // Licznik jest pomocniczy — cisza, storefront musi działać niezależnie.
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $shop = $request->attributes->get('shop');

        if (! $shop instanceof Shop || ! $request->isMethod('GET')) {
            return;
        }

        if ($this->isBot($request) || $this->isOwnStaff($request, $shop)) {
            return;
        }

        $recorder = app(TrafficRecorder::class);

        // Wizyta = raz na sesję/dzień (kolejne podstrony to nie nowe wizyty).
        $visitFlag = 'st_visit_'.$shop->id.'_'.now()->toDateString();
        if (! $request->session()->has($visitFlag)) {
            $recorder->record($shop->id, 'visits');
            $request->session()->put($visitFlag, true);
        }

        // Wyświetlenie produktu = odsłona karty produktu (surowo, bez deduplikacji).
        if ($request->route()?->getName() === 'storefront.product') {
            $recorder->record($shop->id, 'product_views');
        }
    }

    private function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        // Brak user-agenta = nie-przeglądarka → nie liczymy.
        return $agent === '' || preg_match(self::BOT_PATTERN, $agent) === 1;
    }

    /**
     * Właściciel sklepu lub administrator podglądający własny storefront nie jest
     * klientem — inaczej każdy podgląd zawyżałby wizyty i psuł konwersję.
     */
    private function isOwnStaff(Request $request, Shop $shop): bool
    {
        $user = $request->user();

        return $user !== null && ((int) $user->id === (int) $shop->owner_id || $user->isAdmin());
    }
}
