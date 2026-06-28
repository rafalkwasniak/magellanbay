<?php

namespace App\Enums;

/**
 * Stawki VAT dostępne dla produktów. Wartość 'zw' = zwolniony. Lista może być
 * rozszerzana zgodnie z przepisami. Cena produktu jest brutto; netto i kwotę
 * VAT wyliczamy z brutto i ułamka stawki.
 */
enum VatRate: string
{
    case R23 = '23';
    case R8 = '8';
    case R5 = '5';
    case R0 = '0';
    case Zw = 'zw';

    /**
     * Czytelna etykieta do UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Zw => 'zw.',
            default => $this->value.'%',
        };
    }

    /**
     * Ułamek stawki do wyliczeń (zw./0% = 0).
     */
    public function fraction(): float
    {
        return match ($this) {
            self::R23 => 0.23,
            self::R8 => 0.08,
            self::R5 => 0.05,
            default => 0.0,
        };
    }
}
