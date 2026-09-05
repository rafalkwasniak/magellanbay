<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ContentReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ContentReportDecisionRequest;
use App\Models\ContentReport;
use App\Services\ContentReportMailer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Zgłoszenia treści bezprawnych w panelu WŁAŚCICIELA sklepu dedykowanego.
 *
 * DLACZEGO TO ISTNIEJE TYLKO TUTAJ. W Kramio są trzy strony: zgłaszający,
 * platforma (rozstrzyga) i sprzedawca (dostaje zawiadomienie z art. 17). Podział
 * nie jest organizacyjny — na nim stoi nasza kwalifikacja jako dostawcy hostingu:
 * o cudzej treści decyduje operator, nie sprzedawca, którego ta treść dotyczy.
 *
 * W sklepie dedykowanym podmiot jest JEDEN. Właściciel sprzedaje własny towar
 * na własnym serwerze, więc nie ma tu ani hostingu cudzych treści, ani sędziego
 * we własnej sprawie — jest po prostu adresat zgłoszenia. Formularz publiczny
 * przyjmuje pismo, on je czyta i odpowiada.
 *
 * Ekran kończy się na DECYZJI Z UZASADNIENIEM — bez „ukryj produkt" i „zawieś
 * sklep", tak samo jak w konsoli admina. Rozpatrzenie zgłoszenia i akcja na
 * katalogu to dwie różne rzeczy; sklejone w jeden przycisk zostawiłyby jeden
 * ślad zamiast dwóch.
 *
 * @see App\Http\Middleware\EnsureDedicatedMode
 */
class ContentReportController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Renderable
    {
        $shop = $request->user()->shop;
        abort_if($shop === null, 404);

        $status = $request->query('stan');
        $status = is_string($status) ? ContentReportStatus::tryFrom($status) : null;
        $search = trim((string) $request->query('szukaj', ''));

        $base = fn () => ContentReport::query()->whereBelongsTo($shop);

        return view('seller.reports.index', [
            'reports' => $base()
                ->when($status !== null, fn ($q) => $q->where('status', $status))
                // Adres i zgłaszający — dwie rzeczy, po których realnie wraca się
                // do sprawy. Uzasadnień nie przeszukujemy: to długie teksty,
                // a `like` po nich na shared hostingu robi się kosztowne.
                ->when($search !== '', function ($query) use ($search) {
                    $term = '%'.$search.'%';
                    $byReference = ContentReport::idFromReference($search);

                    $query->where(function ($q) use ($term, $byReference) {
                        $q->when($byReference !== null, fn ($r) => $r->orWhere('id', $byReference))
                            ->orWhere('url', 'like', $term)
                            ->orWhere('reporter_email', 'like', $term)
                            ->orWhere('reporter_name', 'like', $term);
                    });
                })
                // Nierozpatrzone na górze niezależnie od daty — to jedyna kolejka,
                // w której zaleganie ma realny koszt.
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ContentReportStatus::New->value])
                ->latest('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'filters' => [
                'stan' => $status?->value ?? '',
                'szukaj' => $search,
            ],
            'statuses' => ContentReportStatus::cases(),
            'counts' => [
                'all' => $base()->count(),
                'pending' => $base()->pending()->count(),
            ],
        ]);
    }

    public function show(Request $request, ContentReport $report): Renderable
    {
        $this->authorizeReport($request, $report);

        return view('seller.reports.show', [
            'report' => $report->load('decidedBy'),
        ]);
    }

    public function decide(
        ContentReportDecisionRequest $request,
        ContentReport $report,
        ContentReportMailer $mailer,
    ): RedirectResponse {
        $this->authorizeReport($request, $report);

        // Bramka na powtórne rozstrzygnięcie: bez niej odświeżony formularz
        // wysłałby zgłaszającemu drugą, sprzeczną decyzję.
        if ($report->status->isDecided()) {
            return redirect()
                ->route('seller.reports.show', $report)
                ->with('error', 'To zgłoszenie zostało już rozpatrzone.');
        }

        $report->forceFill([
            'status' => $request->enum('status', ContentReportStatus::class),
            'decision_reason' => $request->validated('decision_reason'),
            'decided_at' => now(),
            'decided_by' => $request->user()->id,
        ])->save();

        // Do zgłaszającego — z uzasadnieniem i pouczeniem. To jedyny mail, jaki
        // ma tu sens: zawiadomienie „dla sprzedawcy" (art. 17) trafiłoby do
        // właściciela, czyli do osoby, która przed chwilą sama tę decyzję podjęła.
        // Wyłączenie siedzi w ContentReportMailer, nie tutaj — żeby ta sama zasada
        // obowiązywała niezależnie od tego, skąd wołamy.
        $mailer->decision($report);
        $mailer->statementOfReasons($report);

        return redirect()
            ->route('seller.reports.show', $report)
            ->with('success', 'Zgłoszenie rozpatrzone. Powiadomienie dla zgłaszającego poszło do kolejki.');
    }

    /**
     * Zgłoszenie musi dotyczyć sklepu zalogowanego właściciela — inaczej 404
     * (nie zdradzamy istnienia cudzych spraw).
     */
    private function authorizeReport(Request $request, ContentReport $report): void
    {
        abort_unless($report->shop_id === $request->user()->shop?->id, 404);
    }
}
