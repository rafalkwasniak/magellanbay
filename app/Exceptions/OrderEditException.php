<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Rzucany przy edycji zamówienia w panelu sprzedawcy (OrderEditor), gdy operacja
 * jest niedozwolona: ilość poza dostępnym stanem, produkt niedostępny, cena
 * ujemna itp. Komunikat jest przeznaczony dla sprzedawcy (pokazywany w UI).
 * Warstwa Livewire zwykle waliduje wcześniej — to twarda siatka bezpieczeństwa.
 */
class OrderEditException extends RuntimeException
{
}
