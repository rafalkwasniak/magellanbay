<?php

namespace App\Livewire\Administrator;

use App\Exceptions\BulkMailException;
use App\Models\PlatformMailing;
use App\Services\PlatformMailService;
use Livewire\Component;

/**
 * Wysyłka wiadomości do sprzedawców: próbka na własny adres (bez limitu),
 * potwierdzenie w miejscu i jedna nieodwracalna wysyłka.
 *
 * Bliźniak komponentu `Seller\BulkMailingSender` minus karencja — kolejną
 * wiadomość można napisać i wysłać od ręki. Potwierdzenie zostaje, bo maile
 * wychodzą do ludzi i tego się nie cofa.
 */
class PlatformMailingSender extends Component
{
    public PlatformMailing $mailing;

    /** Czy pokazujemy potwierdzenie wysyłki. */
    public bool $confirming = false;

    public ?string $error = null;

    public ?string $sentMessage = null;

    public function mount(PlatformMailing $mailing): void
    {
        $this->mailing = $mailing;
    }

    /**
     * Próbka na adres zalogowanego administratora. Bez limitu — po to jest,
     * żeby sprawdzić treść tyle razy, ile trzeba.
     */
    public function sendTest(PlatformMailService $mail): void
    {
        $this->authorizeAdmin();
        $this->reset(['error', 'sentMessage']);

        $user = auth()->user();

        $mail->sendTest($this->mailing, $user->email, $user->name);

        $this->mailing->refresh();
        $this->sentMessage = 'Próbkę wysłaliśmy na '.$user->email.'. Powinna dotrzeć w ciągu minuty.';
    }

    public function askSend(): void
    {
        $this->authorizeAdmin();
        $this->reset(['error', 'sentMessage']);

        $this->confirming = true;
    }

    public function dismiss(): void
    {
        $this->confirming = false;
    }

    /**
     * Wysyłka do zaznaczonych sprzedawców. Wszystkie reguły (jednorazowość,
     * pusta lista, zgody) pilnuje serwis — tutaj tylko pokazujemy jego
     * komunikat, bo jest pisany do człowieka.
     */
    public function send(PlatformMailService $mail): void
    {
        $this->authorizeAdmin();
        $this->confirming = false;
        $this->reset(['error', 'sentMessage']);

        try {
            $count = $mail->send($this->mailing);
        } catch (BulkMailException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->mailing->refresh();
        $this->sentMessage = 'Wiadomość poszła do '.$count.' '.($count === 1 ? 'sprzedawcy' : 'sprzedawców').'.';
    }

    /**
     * Trasy panelu są za `role:admin`, ale komponent Livewire to osobne
     * wejście — bez tej bramki wysyłkę mógłby wywołać każdy zalogowany.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() === true, 403);
    }

    public function render()
    {
        $mail = app(PlatformMailService::class);

        return view('livewire.administrator.platform-mailing-sender', [
            // Liczba faktycznych adresatów: zaznaczeni PRZECIĘCI z aktualną
            // pulą zgód. Ta sama metoda, której użyje wysyłka — przycisk nie
            // może obiecywać innej liczby, niż naprawdę pójdzie.
            'recipients' => $this->mailing->isSent() ? 0 : $mail->recipients($this->mailing)->count(),
            'eligible' => $mail->eligibleCount(),
            'total' => $this->mailing->messagesTotal(),
            'delivered' => $this->mailing->deliveredCount(),
            'failed' => $this->mailing->failedCount(),
            'delivering' => $this->mailing->isDelivering(),
        ]);
    }
}
