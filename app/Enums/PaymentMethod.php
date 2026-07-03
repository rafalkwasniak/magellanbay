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
}
