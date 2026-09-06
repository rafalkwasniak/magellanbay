<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\LicensorSettlement;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Rozliczenia z partnerami licencyjnymi.
 *
 * Ekran odpowiada na jedno pytanie: komu i ile należy się za wybrany miesiąc.
 * Wszystko, co decyduje o kwotach, siedzi w `LicensorSettlement` — tu zostaje
 * wybór okresu i podanie wyniku dalej.
 *
 * OKRES DOMYŚLNY TO POPRZEDNI MIESIĄC, nie bieżący. Rozlicza się miesiąc,
 * który się skończył; bieżący pokazywałby kwotę rosnącą w trakcie oglądania
 * i kusił do wysłania partnerowi niepełnego zestawienia.
 */
class SettlementController extends Controller
{
    public function index(Request $request, LicensorSettlement $settlement): Renderable
    {
        $shop = $request->user()->shop;
        abort_if($shop === null, 404);

        [$from, $to] = $this->period($request);

        return view('seller.settlements.index', [
            'shop' => $shop,
            'month' => $from->format('Y-m'),
            'from' => $from,
            'to' => $to,
            'summary' => $settlement->summary($shop, $from, $to),
            'rows' => $settlement->rows($shop, $from, $to),
            'months' => $this->months($shop),
        ]);
    }

    public function download(Request $request, LicensorSettlement $settlement): Response
    {
        $shop = $request->user()->shop;
        abort_if($shop === null, 404);

        [$from, $to] = $this->period($request);

        $name = 'rozliczenie-'.$from->format('Y-m').'.xlsx';

        return response($settlement->workbook($shop, $from, $to), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /**
     * Miesiąc z adresu (`?miesiac=2026-03`) albo poprzedni.
     *
     * Zakres jest domknięty od lewej i otwarty od prawej — `< 1 kwietnia`,
     * a nie `<= 31 marca`. Zamówienie złożone 31 marca o 23:30 przy porównaniu
     * z datą wypadłoby z rozliczenia i nie znalazłoby się w żadnym.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $raw = (string) $request->query('miesiac', '');

        try {
            $from = $raw !== ''
                ? Carbon::createFromFormat('Y-m', $raw)->startOfMonth()
                : Carbon::now()->subMonthNoOverflow()->startOfMonth();
        } catch (\Throwable) {
            // Śmieć w adresie ma dać bieżące rozliczenie, a nie błąd.
            $from = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        }

        return [$from, $from->copy()->addMonthNoOverflow()->startOfMonth()];
    }

    /**
     * Miesiące do wyboru: od pierwszego zamówienia sklepu do bieżącego.
     *
     * Lista wprost z danych, a nie sztywne „ostatnie 12": sklep działający od
     * dwóch lat ma się rozliczyć także wstecz, a nowy nie ma oglądać dziesięciu
     * pustych miesięcy sprzed swojego istnienia.
     *
     * @return list<array{value: string, label: string}>
     */
    private function months($shop): array
    {
        $first = $shop->orders()->min('created_at');

        $start = $first !== null
            ? Carbon::parse($first)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $cursor = Carbon::now()->startOfMonth();
        $months = [];

        while ($cursor->greaterThanOrEqualTo($start) && count($months) < 60) {
            $months[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('LLLL Y'),
            ];

            $cursor = $cursor->subMonthNoOverflow();
        }

        return $months;
    }
}
