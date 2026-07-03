<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Rzucany przy finalnej weryfikacji zamówienia (spec „Finalna weryfikacja
 * zamówienia"), gdy dostępność zmieniła się od ostatniego widoku koszyka:
 * koszyk został już uzgodniony, a klientowi pokazujemy komunikaty i prosimy o
 * ponowne złożenie. Zamówienie NIE powstaje.
 */
class CartNeedsReviewException extends RuntimeException
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(public array $messages)
    {
        parent::__construct('Zawartość koszyka wymaga ponownego sprawdzenia.');
    }
}
