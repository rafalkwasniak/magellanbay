<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\LicensorSettlement;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Rozliczenia z partnerami licencyjnymi.
 *
 * Ekran odpowiada na jedno pytanie: komu i ile należy się za wybrany okres.
 * Wszystko, co decyduje o kwotach, siedzi w `LicensorSettlement` — tu zostaje
 * wybór okresu i podanie wyniku dalej.
 *
 * OKRES DOMYŚLNY TO POPRZEDNI MIESIĄC, nie bieżący. Rozlicza się miesiąc,
 * który się skończył; bieżący pokazywałby kwotę rosnącą w trakcie oglądania
 * i kusił do wysłania partnerowi niepełnego zestawienia.
 *
 * ---------------------------------------------------------------------------
 * DLACZEGO OPRÓCZ MIESIĘCY SĄ LATA I „OD POCZĄTKU"
 *
 * Klient rozlicza się z partnerami nie co miesiąc, tylko PO PRZEKROCZENIU
 * UMÓWIONEJ KWOTY, a jeśli próg nie padnie — na koniec roku (odpowiedź
 * z 08.09.2026). Sam miesięczny widok nie odpowiadał na pytanie, które przy
 * takiej umowie pada naprawdę: „ile temu partnerowi uzbierało się w sumie".
 *
 * Sklep nie pilnuje progów i nie zamyka okresów — to świadoma granica. Sprzedaż
 * spoza sklepu (gotówka na zawodach) nigdy tu nie trafi, więc automat liczyłby
 * progi na niepełnych danych i wyglądał przy tym na źródło prawdy. Pokazujemy
 * sumę za dowolny zakres i na tym kończymy; decyzja o wypłacie należy do
 * właściciela, a umówioną zasadę trzyma notatka przy partnerze.
 */
class SettlementController extends Controller
{
    /**
     * Wartość okresu „cała historia sklepu" w adresie.
     */
    private const EVERYTHING = 'wszystko';

    public function index(Request $request, LicensorSettlement $settlement): Renderable
    {
        $shop = $request->user()->currentShop();
        abort_if($shop === null, 404);

        $period = $this->period($request, $shop);
        [$from, $to] = [$period['from'], $period['to']];

        return view('seller.settlements.index', [
            'shop' => $shop,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'summary' => $settlement->summary($shop, $from, $to),
            'rows' => $settlement->rows($shop, $from, $to),
            'periods' => $this->periods($shop),
            'notes' => $this->notes($shop),
        ]);
    }

    public function download(Request $request, LicensorSettlement $settlement): Response
    {
        $shop = $request->user()->currentShop();
        abort_if($shop === null, 404);

        $period = $this->period($request, $shop);

        $name = 'rozliczenie-'.$period['value'].'.xlsx';

        return response($settlement->workbook($shop, $period['from'], $period['to']), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /**
     * Okres z adresu (`?okres=`) — miesiąc `2026-03`, rok `2026` albo
     * `wszystko`. Bez parametru: poprzedni miesiąc.
     *
     * Zakres jest domknięty od lewej i otwarty od prawej — `< 1 kwietnia`,
     * a nie `<= 31 marca`. Zamówienie złożone 31 marca o 23:30 przy porównaniu
     * z datą wypadłoby z rozliczenia i nie znalazłoby się w żadnym.
     *
     * @return array{value: string, label: string, from: Carbon, to: Carbon}
     */
    private function period(Request $request, Shop $shop): array
    {
        $raw = trim((string) $request->query('okres', ''));

        if ($raw === self::EVERYTHING) {
            return [
                'value' => self::EVERYTHING,
                'label' => 'Od początku',
                'from' => $this->firstSaleDay($shop),
                'to' => $this->endOfToday(),
            ];
        }

        if (preg_match('/^\d{4}$/', $raw) === 1) {
            $from = Carbon::createFromDate((int) $raw, 1, 1)->startOfDay();

            return [
                'value' => $raw,
                'label' => 'Rok '.$raw,
                'from' => $from,
                'to' => $from->copy()->addYear(),
            ];
        }

        try {
            $from = $raw !== ''
                ? Carbon::createFromFormat('Y-m', $raw)->startOfMonth()
                : Carbon::now()->subMonthNoOverflow()->startOfMonth();
        } catch (\Throwable) {
            // Śmieć w adresie ma dać bieżące rozliczenie, a nie błąd.
            $from = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        }

        return [
            'value' => $from->format('Y-m'),
            'label' => $from->translatedFormat('LLLL Y'),
            'from' => $from,
            'to' => $from->copy()->addMonthNoOverflow()->startOfMonth(),
        ];
    }

    /**
     * Okresy do wyboru, w dwóch grupach: zbiorcze i miesiące.
     *
     * Lista wprost z danych, a nie sztywne „ostatnie 12": sklep działający od
     * dwóch lat ma się rozliczyć także wstecz, a nowy nie ma oglądać dziesięciu
     * pustych miesięcy sprzed swojego istnienia.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function periods(Shop $shop): array
    {
        $start = $this->firstSaleDay($shop)->startOfMonth();
        $now = Carbon::now();

        $totals = [['value' => self::EVERYTHING, 'label' => 'Od początku']];

        for ($year = $now->year; $year >= $start->year && $now->year - $year < 20; $year--) {
            $totals[] = ['value' => (string) $year, 'label' => 'Rok '.$year];
        }

        $cursor = $now->copy()->startOfMonth();
        $months = [];

        while ($cursor->greaterThanOrEqualTo($start) && count($months) < 60) {
            $months[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('LLLL Y'),
            ];

            $cursor = $cursor->subMonthNoOverflow();
        }

        return ['Okresy zbiorcze' => $totals, 'Miesiące' => $months];
    }

    /**
     * Notatki z kartoteki partnerów, po identyfikatorze.
     *
     * Trafiają na ekran rozliczeń, bo tam są potrzebne: to w notatce sprzedawca
     * trzyma umówioną z partnerem zasadę („rozliczenie po przekroczeniu 1000 zł,
     * faktura kwartalnie"). Sklep jej nie wykonuje — ma ją przypomnieć obok
     * liczby, przy której zapada decyzja.
     *
     * @return array<int, string>
     */
    private function notes(Shop $shop): array
    {
        return $shop->licensors()
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->pluck('notes', 'id')
            ->all();
    }

    /**
     * Dzień pierwszej sprzedaży sklepu — początek zakresu „od początku".
     */
    private function firstSaleDay(Shop $shop): Carbon
    {
        $first = $shop->orders()->min('created_at');

        return $first !== null
            ? Carbon::parse($first)->startOfDay()
            : Carbon::now()->startOfDay();
    }

    /**
     * Granica prawa dla zakresów kończących się „dziś".
     *
     * Jutro o północy, a nie `now()`: zakres jest otwarty od prawej, więc
     * zamówienie złożone kwadrans temu musi się w nim zmieścić razem z każdym
     * następnym, które dojdzie do końca dnia.
     */
    private function endOfToday(): Carbon
    {
        return Carbon::now()->addDay()->startOfDay();
    }
}
