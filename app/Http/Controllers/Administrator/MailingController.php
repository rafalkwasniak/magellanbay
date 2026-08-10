<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrator\PlatformMailingRequest;
use App\Models\PlatformMailing;
use App\Services\PlatformMailService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;

/**
 * Wiadomości platformy do sprzedawców — odpowiednik „Wiadomości do klientów"
 * z panelu sprzedawcy, ale bez bramki pakietu (to narzędzie właściciela),
 * bez promowanego produktu i bez karencji.
 *
 * Kontroler odpowiada wyłącznie za SZKIC: utworzenie, edycję i skasowanie.
 * Wybór odbiorców i samą wysyłkę prowadzą komponenty Livewire — pierwszy
 * potrzebuje odświeżania listy przy szukaniu, drugi potwierdzenia w miejscu
 * i licznika postępu bez przeładowania strony.
 */
class MailingController extends Controller
{
    public function index(PlatformMailService $mail): Renderable
    {
        return view('administrator.mailings.index', [
            // Postęp wysyłki liczymy podzapytaniami (`withCount`), a nie
            // zapytaniem na wiersz — lista ma 10 pozycji, a każda mogłaby
            // odpytywać outbox o setki maili.
            'mailings' => PlatformMailing::query()
                ->withCount([
                    'messages',
                    'messages as delivered_count' => fn ($query) => $query->whereNotNull('sent_at'),
                    'messages as failed_count' => fn ($query) => $query->whereNotNull('failed_at'),
                ])
                ->latest('id')
                ->paginate(10),
            'eligible' => $mail->eligibleCount(),
        ]);
    }

    public function create(): Renderable
    {
        return view('administrator.mailings.form', ['mailing' => null]);
    }

    public function store(PlatformMailingRequest $request): RedirectResponse
    {
        $mailing = PlatformMailing::create($request->validated());

        // Prosto do edycji: dopiero tam są odbiorcy i wysyłka próbki, a zapisany
        // szkic jest ich warunkiem.
        return redirect()
            ->route('administrator.mailings.edit', $mailing)
            ->with('success', 'Szkic zapisany. Wybierz odbiorców i wyślij próbkę do siebie.');
    }

    public function edit(PlatformMailing $mailing): Renderable
    {
        return view('administrator.mailings.form', ['mailing' => $mailing]);
    }

    public function update(PlatformMailingRequest $request, PlatformMailing $mailing): RedirectResponse
    {
        $this->guardDraft($mailing);

        $mailing->update($request->validated());

        return redirect()
            ->route('administrator.mailings.edit', $mailing)
            ->with('success', 'Zmiany zapisane.');
    }

    public function destroy(PlatformMailing $mailing): RedirectResponse
    {
        $this->guardDraft($mailing);

        $mailing->delete();

        return redirect()
            ->route('administrator.mailings.index')
            ->with('success', 'Szkic usunięty.');
    }

    /**
     * Wysłanej wiadomości nie wolno już zmieniać ani kasować — sprzedawcy mają
     * ją w skrzynkach, więc zapis musi zostać zgodny z tym, co dostali.
     *
     * To jedyne, co po wysyłce jest zamknięte. Karencji nie ma: kolejną
     * wiadomość tworzysz i wysyłasz od ręki.
     */
    private function guardDraft(PlatformMailing $mailing): void
    {
        abort_if($mailing->isSent(), 403);
    }
}
