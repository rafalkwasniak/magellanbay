<?php

namespace App\Livewire\Administrator;

use App\Models\PlatformMailing;
use App\Models\User;
use App\Services\PlatformMailService;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Wybór odbiorców wiadomości platformy: lista sprzedawców ze zgodą, checkboxy,
 * szukajka i przyciski „zaznacz / odznacz".
 *
 * Zapisuje się NA BIEŻĄCO, bez osobnego „Zapisz". Wybór odbiorców to nie część
 * formularza z treścią — administrator klika w listę i wraca do pisania, a
 * zgubienie zaznaczenia przy zapisie treści byłoby wkurzające.
 *
 * PUŁAPKA, o którą łatwo się potknąć: przyciski „zaznacz / odznacz" działają na
 * to, CO WIDAĆ po odfiltrowaniu szukajką — bo po to szukajka i checkboxy stoją
 * obok siebie („znajdź Kowalskich, zaznacz ich"). Dlatego przy aktywnej
 * szukajce zmieniają napis na „znalezionych", inaczej kliknięcie „Odznacz
 * wszystkich" przy wpisanej frazie kasowałoby wybór spoza wyników.
 */
class PlatformMailingRecipients extends Component
{
    public PlatformMailing $mailing;

    /**
     * Zaznaczone identyfikatory. Trzymane jako ŁAŃCUCHY, bo tak przychodzą
     * z checkboxów w przeglądarce — mieszanie ich z liczbami sprawia, że część
     * pól przestaje się zaznaczać.
     *
     * @var array<int, string>
     */
    public array $selected = [];

    public string $search = '';

    public function mount(PlatformMailing $mailing): void
    {
        $this->mailing = $mailing;

        // Świeży szkic nie ma jeszcze wyboru — domyślnie zaznaczamy wszystkich,
        // bo w większości wypadków pisze się do wszystkich. Serwis czyta ten
        // sam domyślny stan z `recipient_ids = null`, więc ekran i wysyłka
        // mówią to samo, nawet jeśli nikt w listę nie kliknie.
        $this->selected = $mailing->hasRecipientSelection()
            ? array_map('strval', $mailing->recipientIds())
            : $this->eligible()->map(fn (User $user) => (string) $user->getKey())->all();
    }

    /**
     * Każde kliknięcie w checkbox zapisuje wybór.
     */
    public function updatedSelected(): void
    {
        $this->persist();
    }

    public function selectVisible(): void
    {
        $visible = $this->visible()->map(fn (User $user) => (string) $user->getKey())->all();

        $this->selected = array_values(array_unique([...$this->selected, ...$visible]));

        $this->persist();
    }

    public function deselectVisible(): void
    {
        $visible = $this->visible()->map(fn (User $user) => (string) $user->getKey())->all();

        $this->selected = array_values(array_diff($this->selected, $visible));

        $this->persist();
    }

    /**
     * Zapis wyboru na wiadomości. `recipient_ids` świadomie nie jest
     * mass-assignable — listę odbiorców ustawia wyłącznie ten komponent.
     */
    private function persist(): void
    {
        $this->authorizeDraft();

        $this->mailing->forceFill([
            'recipient_ids' => array_values(array_map('intval', $this->selected)),
        ])->save();
    }

    /**
     * Pełna pula: sprzedawcy z aktywną zgodą na treści handlowe.
     *
     * @return Collection<int, User>
     */
    private function eligible(): Collection
    {
        return app(PlatformMailService::class)->eligible();
    }

    /**
     * Pula zawężona szukajką — po nazwisku, imieniu, adresie e-mail ORAZ nazwie
     * sklepu. Sklep w szukajce, bo administrator kojarzy sprzedawcę głównie po
     * tym, co on sprzedaje — nazwisko zna rzadziej niż nazwę kramu.
     *
     * @return Collection<int, User>
     */
    private function visible(): Collection
    {
        $term = trim($this->search);

        if ($term === '') {
            return $this->eligible();
        }

        return $this->eligible()->filter(function (User $user) use ($term) {
            $haystack = mb_strtolower(implode(' ', array_filter([
                trim($user->name.' '.$user->surname),
                $user->email,
                $user->shop?->name,
            ])));

            return str_contains($haystack, mb_strtolower($term));
        })->values();
    }

    /**
     * Wysłanej wiadomości nie wolno już przeadresować — sprzedawcy mają ją
     * w skrzynkach. Dostęp do samego komponentu chroni też rola: trasy panelu
     * są za `role:admin`, ale komponent Livewire jest osobnym wejściem.
     */
    private function authorizeDraft(): void
    {
        abort_unless(auth()->user()?->isAdmin() === true, 403);
        abort_if($this->mailing->isSent(), 403);
    }

    public function render()
    {
        $visible = $this->visible();

        return view('livewire.administrator.platform-mailing-recipients', [
            'people' => $visible,
            'eligibleCount' => $this->eligible()->count(),
            'selectedCount' => count($this->selected),
            'searching' => trim($this->search) !== '',
        ]);
    }
}
