<?php

namespace App\Enums;

/**
 * Metody płatności (MVP). Przelew tradycyjny na konto oraz płatność przy
 * odbiorze. Płatności online (operator) dojdą później w wyższych pakietach.
 */
enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case PayOnPickup = 'pay_on_pickup';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Przelew na konto',
            self::PayOnPickup => 'Płatność przy odbiorze',
        };
    }

    /**
     * Czy pieniądze mają wpłynąć PRZED wydaniem towaru. To rozstrzyga o ścieżce
     * statusów (`OrderFlow`): przedpłata zaczyna od „Oczekuje na płatność" i ma
     * krok „Opłacone", płatność przy odbiorze nie ma czego potwierdzać. Płatności
     * online (operator) dojdą jako przedpłata z auto-przejściem z webhooka.
     */
    public function isPrepaid(): bool
    {
        return match ($this) {
            self::BankTransfer => true,
            self::PayOnPickup => false,
        };
    }
}
