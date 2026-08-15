<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\ContentReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administrator\ContentReportDecisionRequest;
use App\Models\ContentReport;
use App\Services\ContentReportMailer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Konsola admina — zgłoszenia treści bezprawnych (art. 16 DSA).
 *
 * Ekran kończy się na DECYZJI Z UZASADNIENIEM. Świadomie nie ma tu przycisków
 * „ukryj produkt" ani „zawieś sklep": rozpatrzenie zgłoszenia i akcja moderacyjna
 * to dwie różne rzeczy, a sklejone w jeden przycisk zostawiłyby w rejestrze jeden
 * ślad zamiast dwóch. Do czasu, aż okaże się, że klikamy to regularnie, akcje
 * robimy w dziale „Sklepy" (decyzja Rafała: na razie minimalnie).
 *
 * Decyzji NIE DA SIĘ cofnąć z tego ekranu — po rozstrzygnięciu wychodzą maile do
 * zgłaszającego i (przy uznaniu) do sprzedawcy. Zmiana zdania to nowa
 * korespondencja, nie podmiana wiersza.
 */
class ContentReportController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Renderable
    {
        $status = $request->query('stan');
        $status = is_string($status) ? ContentReportStatus::tryFrom($status) : null;
        $search = trim((string) $request->query('szukaj', ''));

        return view('administrator.reports.index', [
            'reports' => ContentReport::query()
                ->with('shop')
                ->when($status !== null, fn ($q) => $q->where('status', $status))
                // Szukamy po adresie i zgłaszającym — dwie rzeczy, po których
                // realnie wraca się do sprawy („znowu ten sam sklep", „ten sam
                // zgłaszający"). Uzasadnienia nie przeszukujemy: to długie teksty,
                // a `like` po nich na shared hostingu robi się kosztowne.
                ->when($search !== '', function ($query) use ($search) {
                    $term = '%'.$search.'%';
                    // Wklejony numer sprawy („ZG-000042", „zg-42", samo „42")
                    // ma trafiać w jedno zgłoszenie, a nie szukać tego ciągu
                    // w adresach — po numerze wraca się do KONKRETNEJ sprawy.
                    $byReference = ContentReport::idFromReference($search);

                    $query->where(function ($q) use ($term, $byReference) {
                        $q->when($byReference !== null, fn ($r) => $r->orWhere('id', $byReference))
                            ->orWhere('url', 'like', $term)
                            ->orWhere('reporter_email', 'like', $term)
                            ->orWhere('reporter_name', 'like', $term)
                            ->orWhereHas('shop', fn ($s) => $s
                                ->where('name', 'like', $term)
                                ->orWhere('slug', 'like', $term));
                    });
                })
                // Nierozpatrzone na górze niezależnie od daty — to jedyna kolejka,
                // w której zaleganie ma realny koszt (art. 6 DSA: wiedza już jest).
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
                'all' => ContentReport::count(),
                'pending' => ContentReport::pending()->count(),
            ],
        ]);
    }

    public function show(ContentReport $report): Renderable
    {
        return view('administrator.reports.show', [
            'report' => $report->load('shop.owner', 'decidedBy'),
        ]);
    }

    public function decide(
        ContentReportDecisionRequest $request,
        ContentReport $report,
        ContentReportMailer $mailer,
    ): RedirectResponse {
        // Bramka na powtórne rozstrzygnięcie: bez niej odświeżony formularz
        // wysłałby zgłaszającemu drugą, sprzeczną decyzję.
        if ($report->status->isDecided()) {
            return redirect()
                ->route('administrator.reports.show', $report)
                ->with('error', 'To zgłoszenie zostało już rozpatrzone.');
        }

        $report->forceFill([
            'status' => $request->enum('status', ContentReportStatus::class),
            'decision_reason' => $request->validated('decision_reason'),
            'decided_at' => now(),
            'decided_by' => $request->user()->id,
        ])->save();

        $mailer->decision($report);
        $mailer->statementOfReasons($report);

        return redirect()
            ->route('administrator.reports.show', $report)
            ->with('success', 'Zgłoszenie rozpatrzone. Powiadomienia poszły do kolejki.');
    }
}
