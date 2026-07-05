<?php

namespace App\Enums;

/**
 * Jednostka sprzedaży produktu: na sztuki albo na wagę (kg). Decyduje, jak
 * podajemy i formatujemy ILOŚĆ — cena zostaje „za 1 jednostkę" niezależnie od
 * wyboru (1 szt. albo 1 kg). To jedyne źródło prawdy o jednostce: skrót do UI,
 * krok steppera, minimalna ilość i formatowanie liczby żyją tutaj, żeby koszyk,
 * kasa, maile, karta produktu i listing mówiły jednym głosem.
 *
 * Wartość EN stała (piece|weight) — konwencja „kod po angielsku"; etykiety PL.
 */
enum SaleUnit: string
{
    case Piece = 'piece';
    case Weight = 'weight';

    /**
     * Etykieta do wyboru w formularzu („Na sztuki" / „Na wagę (kg)").
     */
    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Na sztuki',
            self::Weight => 'Na wagę (kg)',
        };
    }

    /**
     * Skrót jednostki widoczny przy ilości („szt." / „kg").
     */
    public function abbreviation(): string
    {
        return match ($this) {
            self::Piece => 'szt.',
            self::Weight => 'kg',
        };
    }

    /**
     * Przyrostek ceny jednostkowej („/szt." / „/kg").
     */
    public function perUnit(): string
    {
        return '/'.$this->abbreviation();
    }

    /**
     * Sprzedaż na wagę? Ilość jest wtedy ułamkowa (krok 0,5 kg, min 0,5 kg).
     */
    public function isWeight(): bool
    {
        return $this === self::Weight;
    }

    /**
     * Krok steppera „+/−": 1 szt. albo 0,5 kg. Dokładniejszą wagę klient wpisuje
     * z palca (np. 1,20 kg) — krok to tylko wygoda przycisków.
     */
    public function step(): float
    {
        return $this->isWeight() ? 0.5 : 1.0;
    }

    /**
     * Minimalna ilość w koszyku: 1 szt. albo 0,5 kg (podłoga = krok). Poniżej
     * schodzi tylko usunięcie pozycji, nie „−".
     */
    public function minQuantity(): float
    {
        return $this->step();
    }

    /**
     * Sprowadza wpisaną ilość do dopuszczalnej postaci: waga zaokrąglona do 2
     * miejsc (granulacja 10 g), sztuki do liczby całkowitej. Wartość poniżej
     * minimum lub ≤ 0 → 0.0 (pozycja do usunięcia). Nie przycina do stanu — to
     * robi CartService (zna produkt).
     */
    public function normalizeQuantity(float $qty): float
    {
        $qty = $this->isWeight() ? round($qty, 2) : (float) (int) round($qty);

        return $qty < $this->minQuantity() ? 0.0 : $qty;
    }

    /**
     * Sama liczba w polskim formacie: „2,50" (kg, zawsze 2 miejsca) albo „3"
     * (szt., bez części dziesiętnej).
     */
    public function formatAmount(float $qty): string
    {
        return $this->isWeight()
            ? number_format($qty, 2, ',', "\u{a0}")
            : (string) (int) round($qty);
    }

    /**
     * Ilość z jednostką: „2,50 kg" / „3 szt.". Główna metoda dla widoków.
     */
    public function formatQuantity(float $qty): string
    {
        return $this->formatAmount($qty).' '.$this->abbreviation();
    }

    /**
     * Ilość do EDYTOWALNEGO pola koszyka — przecinek, bez zbędnych zer
     * („2,50" → „2,5"; „3,00" → „3"). Sztuki: liczba całkowita.
     */
    public function inputAmount(float $qty): string
    {
        if (! $this->isWeight()) {
            return (string) (int) round($qty);
        }

        $formatted = number_format($qty, 2, ',', '');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
