<?php

namespace App\Services\Shipping;

use App\Enums\ParcelSize;

/**
 * Opis paczki przekazywany do nadania. Istnieje, bo InPost opisuje przesyłkę
 * DWOMA różnymi językami i nie da się ich pogodzić w jednym polu:
 *
 *  - PACZKOMAT — szablon skrytki (`template: small|medium|large` = gabaryt
 *    A/B/C). Wymiary są wtedy wymiarami SKRYTKI, nie paczki, i ShipX uzupełnia
 *    je sam.
 *  - KURIER — wymiary i waga realnej paczki. Szablony nie mają tu sensu.
 *
 * Trzymanie tego w jednym polu dałoby wartość, która raz znaczy „A", a raz
 * „30×20×10", więc zamiast tego mamy jeden obiekt z dwoma konstruktorami.
 *
 * PRZELICZENIE JEDNOSTEK ŻYJE TYLKO TUTAJ. Sprzedawca podaje centymetry (tak
 * samo jak panel InPostu), baza trzyma centymetry, a ShipX przyjmuje WYŁĄCZNIE
 * milimetry i kilogramy. Jedno miejsce zamiany = jedno miejsce, w którym można
 * się pomylić.
 *
 * Obiekt jedzie przez kolejkę w zadaniu nadania, dlatego jest prosty i
 * niezmienny — bez relacji i bez modeli w środku.
 */
final class ParcelSpec
{
    private function __construct(
        public readonly ?ParcelSize $size,
        public readonly ?int $lengthCm,
        public readonly ?int $widthCm,
        public readonly ?int $heightCm,
        public readonly ?float $weightKg,
    ) {}

    /**
     * Paczka do paczkomatu — opisana gabarytem skrytki.
     */
    public static function locker(ParcelSize $size): self
    {
        return new self($size, null, null, null, null);
    }

    /**
     * Paczka kurierska — wymiary w centymetrach, waga w kilogramach.
     */
    public static function courier(int $lengthCm, int $widthCm, int $heightCm, float $weightKg): self
    {
        return new self(null, $lengthCm, $widthCm, $heightCm, $weightKg);
    }

    public function isLocker(): bool
    {
        return $this->size !== null;
    }

    /**
     * Element tablicy `parcels` w żądaniu ShipX.
     *
     * @return array<string, mixed>
     */
    public function toShipxParcel(): array
    {
        if ($this->isLocker()) {
            return ['template' => $this->size->value];
        }

        return [
            'dimensions' => [
                'length' => $this->lengthCm * 10,
                'width' => $this->widthCm * 10,
                'height' => $this->heightCm * 10,
                'unit' => 'mm',
            ],
            'weight' => [
                'amount' => $this->weightKg,
                'unit' => 'kg',
            ],
        ];
    }

    /**
     * Migawka do zapisania przy zamówieniu. Kolumny wykluczają się nawzajem:
     * przesyłka paczkomatowa nie ma wymiarów paczki, kurierska nie ma gabarytu.
     *
     * @return array<string, mixed>
     */
    public function toOrderColumns(): array
    {
        return [
            'shipment_size' => $this->size,
            'shipment_length_cm' => $this->lengthCm,
            'shipment_width_cm' => $this->widthCm,
            'shipment_height_cm' => $this->heightCm,
            'shipment_weight_kg' => $this->weightKg,
        ];
    }
}
