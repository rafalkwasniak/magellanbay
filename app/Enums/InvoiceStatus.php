<?php

namespace App\Enums;

/**
 * Przejściowy stan generowania faktury VAT (kolumna `invoice_status` na orders).
 * Tylko stany NIEostateczne: brak wartości (NULL) znaczy „idle albo już
 * wystawiona" — o tym drugim rozstrzyga `invoice_id`. Dzięki temu UI karty
 * zamówienia wie, czy pokazać „Stwórz fakturę", „FV w przygotowaniu", „Pobierz
 * FV" czy komunikat o błędzie z ponowieniem.
 */
enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Failed = 'failed';
}
