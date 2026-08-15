<?php

namespace App\Http\Controllers;

use App\Enums\ContentReportCategory;
use App\Http\Requests\ContentReportRequest;
use App\Models\ContentReport;
use App\Services\ContentReportMailer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Publiczny mechanizm zgłaszania treści bezprawnych (art. 16 DSA).
 *
 * Formularz mieszka na CENTRALI, choć zgłaszane treści leżą na storefrontach —
 * bo obowiązek jest nasz, nie sprzedawcy. Gdyby formularz stał na subdomenie,
 * wyglądałby na skrzynkę osoby, której treść się kwestionuje. Linki w stopkach
 * storefrontów muszą więc iść przez `Central::url()`, nie przez `route()`.
 */
class ContentReportController extends Controller
{
    public function create(Request $request): Renderable
    {
        return view('reports.create', [
            'categories' => ContentReportCategory::options(),
            // Adres zgłaszanej strony można podać linkiem („?adres=..."), żeby
            // zgłaszający nie musiał go przepisywać ze stopki storefrontu.
            'prefilledUrl' => (string) $request->query('adres', ''),
        ]);
    }

    public function store(ContentReportRequest $request, ContentReportMailer $mailer): RedirectResponse
    {
        $report = new ContentReport($request->validated());
        $report->ip_address = $request->ip();

        // Sklep rozwiązujemy po stronie serwera z podanego adresu — pole z
        // formularza nie może wskazać, kogo zgłoszenie dotyczy.
        $report->shop()->associate(ContentReport::shopFromUrl($request->validated('url')));
        $report->save();

        // Potwierdzenie odbioru „bez zbędnej zwłoki" (art. 16 ust. 4). Trafia do
        // outboxu, nie leci w locie — zgłaszający nie ma czekać na SMTP, a padnięta
        // wysyłka nie może wywrócić przyjęcia zgłoszenia.
        $mailer->acknowledge($report);

        // `success`, nie `status`: pierwsze daje zielony toast (operacja się
        // udała), drugie bursztynowy komunikat informacyjny. Wariant wybiera
        // `x-toasts` po kluczu sesji, więc pomyłka w kluczu = zły kolor.
        return redirect()
            ->route('reports.create')
            ->with('success', 'Zgłoszenie przyjęte pod numerem '.$report->reference().'. Potwierdzenie wysłaliśmy na podany adres e-mail, a o rozstrzygnięciu napiszemy osobno wraz z uzasadnieniem.');
    }
}
