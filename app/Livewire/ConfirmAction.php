<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Potwierdzenie akcji W MIEJSCU — nakładka na kafelku zamiast okienka
 * przeglądarki.
 *
 * Powód nie jest kosmetyczny. `confirm()` umie wyświetlić jedno zdanie bez
 * formatowania, a akcje na kafelku bywają nieoczywiste: usunięcie to DWIE
 * różne operacje (produkt zamawiany zostaje ukryty dla historii, nigdy
 * niezamawiany znika razem ze zdjęciami), a kopiowanie tworzy drugi produkt
 * pod własnym adresem. Nakładka mówi, co dokładnie nastąpi, zanim ktoś kliknie.
 *
 * Dlaczego pytamy też przed akcją, która nic nie psuje: ikony stoją obok
 * siebie, więc pomyłka „Edytuj / Kopiuj" jest łatwa, a jej skutek CICHY —
 * sprzedawca ląduje w edycji kopii, poprawia ją w przekonaniu, że poprawia
 * oryginał, i zostaje z dwoma produktami zamiast jednego. Kopia jest ukryta,
 * więc w sklepie nic tego nie zdradzi.
 *
 * Komponent jest CELOWO bezczynny: trzyma tylko stan „czy pytamy" i rysuje
 * treść, którą dostał. Samą akcję wykonuje zwykły formularz POST na trasę
 * kontrolera — dzięki temu autoryzacja, sprzątanie i komunikat zostają tam,
 * gdzie były, a komponent nie dubluje ani jednej reguły. To też powód, dla
 * którego nie sprawdza tu uprawnień: nie potrafi nic zmienić w bazie, a POST
 * i tak przechodzi przez bramę trasy.
 *
 * Nakładka pozycjonuje się `absolute inset-0`, więc rodzic (kafelek, wiersz)
 * musi mieć `relative`.
 */
class ConfirmAction extends Component
{
    /** Adres, na który poleci POST po potwierdzeniu. */
    public string $action;

    /** Pytanie w pierwszej linii, np. „Usunąć magnes Mauritius?". */
    public string $title;

    /** Co się stanie — po jednym zdaniu na wiersz. */
    public array $lines = [];

    /** Podpis przycisku potwierdzenia. */
    public string $confirmLabel = 'Tak, usuń';

    /** Nazwa akcji dla czytnika ekranu i dymka nad ikoną. */
    public string $label = 'Usuń';

    /** Podpis widoczny przy ikonie; pusty = sama ikona. */
    public string $text = '';

    /** Ikona przycisku: `trash` albo `copy`. */
    public string $icon = 'trash';

    /**
     * Wydźwięk nakładki: `rose` dla tego, co niszczy, `amber` dla tego, co
     * tylko wymaga uwagi. Klasy Tailwinda muszą być pełnymi napisami (build
     * ich nie znajdzie, gdyby powstawały ze sklejania), dlatego widok wybiera
     * między dwoma gotowymi zestawami, a nie buduje nazw z tej wartości.
     */
    public string $tone = 'rose';

    public bool $asking = false;

    public function ask(): void
    {
        $this->asking = true;
    }

    public function cancel(): void
    {
        $this->asking = false;
    }

    public function render()
    {
        return view('livewire.confirm-action');
    }
}
