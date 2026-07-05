<?php

namespace Tests\Unit;

use App\Enums\SaleUnit;
use PHPUnit\Framework\TestCase;

/**
 * Jednostka sprzedaży — jedno źródło prawdy o skrócie, kroku, minimum i
 * formatowaniu ilości. Waga: 2 miejsca po przecinku, krok/min 0,5 kg; sztuki:
 * liczba całkowita, krok/min 1.
 */
class SaleUnitTest extends TestCase
{
    public function test_piece_formats_as_whole_number(): void
    {
        $this->assertSame('3 szt.', SaleUnit::Piece->formatQuantity(3.0));
        $this->assertSame('3', SaleUnit::Piece->formatAmount(3.0));
        $this->assertSame('szt.', SaleUnit::Piece->abbreviation());
        $this->assertSame('/szt.', SaleUnit::Piece->perUnit());
        $this->assertSame(1.0, SaleUnit::Piece->step());
        $this->assertSame(1.0, SaleUnit::Piece->minQuantity());
        $this->assertFalse(SaleUnit::Piece->isWeight());
    }

    public function test_weight_formats_with_two_decimals_and_comma(): void
    {
        // Twarda spacja jako separator tysięcy — nie wpływa na 2,50.
        $this->assertSame('2,50 kg', SaleUnit::Weight->formatQuantity(2.5));
        $this->assertSame('1,20', SaleUnit::Weight->formatAmount(1.2));
        $this->assertSame('kg', SaleUnit::Weight->abbreviation());
        $this->assertSame(0.5, SaleUnit::Weight->step());
        $this->assertSame(0.5, SaleUnit::Weight->minQuantity());
        $this->assertTrue(SaleUnit::Weight->isWeight());
    }

    public function test_normalize_snaps_and_floors_by_unit(): void
    {
        // Sztuki: zaokrąglenie do całości, poniżej 1 → 0 (usunięcie).
        $this->assertSame(3.0, SaleUnit::Piece->normalizeQuantity(3.4));
        $this->assertSame(0.0, SaleUnit::Piece->normalizeQuantity(0.4));

        // Waga: do 2 miejsc (10 g), poniżej 0,5 kg → 0 (usunięcie).
        $this->assertSame(1.24, SaleUnit::Weight->normalizeQuantity(1.238));
        $this->assertSame(0.5, SaleUnit::Weight->normalizeQuantity(0.5));
        $this->assertSame(0.0, SaleUnit::Weight->normalizeQuantity(0.2));
    }
}
