<?php

namespace App\Enums;

/**
 * Metody płatności. Przelew tradycyjny na konto, płatność przy odbiorze oraz
 * płatność online przez operatora (Paynow/mBank). Metoda jest świadomie
 * operator-agnostyczna: „online" to jeden sposób płatności, a KTÓRA bramka i
 * czyimi kluczami — to szczegół integracji w `shop_integrations`, nie enuma.
 */
enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case PayOnPickup = 'pay_on_pickup';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Przelew na konto',
            self::PayOnPickup => 'Płatność przy odbiorze',
            self::Online => 'Płatność online (BLIK, karta, przelew)',
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
            // Online = przedpłata: reużywa ścieżki „Oczekuje na płatność → Opłacone
            // → …", ale do „Opłacone" przenosi webhook operatora, nie sprzedawca.
            self::Online => true,
        };
    }
}
