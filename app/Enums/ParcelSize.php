<?php

namespace App\Enums;

/**
 * Gabaryt przesyłki paczkomatowej InPost. Wartość enumu to nazwa szablonu
 * ShipX (`parcels[].template`) — kod po angielsku, etykieta po polsku, zgodnie
 * z konwencją projektu.
 *
 * Mapowanie small/medium/large → gabaryt A/B/C potwierdzone empirycznie na
 * sandboxie 2026-08-07 (nadanie z `template: small` wróciło jako `size: "A"`
 * w trackingu i wydrukowało „GABARYT A" na etykiecie).
 *
 * Wymiary są SKRYTKI paczkomatu, nie paczki — sprzedawca dobiera gabaryt do
 * tego, co się w niej zmieści. Waga do 25 kg jest wspólna dla wszystkich.
 */
enum ParcelSize: string
{
    case A = 'small';
    case B = 'medium';
    case C = 'large';

    /**
     * Etykieta z wymiarami — sprzedawca wybiera „na oko", więc sam symbol
     * gabarytu bez centymetrów nic mu nie mówi.
     */
    public function label(): string
    {
        return match ($this) {
            self::A => 'Gabaryt A — 8 × 38 × 64 cm',
            self::B => 'Gabaryt B — 19 × 38 × 64 cm',
            self::C => 'Gabaryt C — 41 × 38 × 64 cm',
        };
    }

    /**
     * Podpowiedź „co się mieści" — po to, by wybór nie wymagał linijki.
     */
    public function hint(): string
    {
        return match ($this) {
            self::A => 'koperta, książka, drobna biżuteria',
            self::B => 'pudełko po butach, ubranie',
            self::C => 'większe pudło, sprzęt',
        };
    }

    /**
     * Sam symbol gabarytu (A/B/C) — do zwięzłych miejsc: listy, plakietki.
     */
    public function symbol(): string
    {
        return $this->name;
    }
}
