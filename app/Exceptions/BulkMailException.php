<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Rzucany, gdy korespondencji seryjnej nie wolno wysłać: brak uprawnienia
 * pakietu, mailing już poszedł, trwa karencja albo nie ma ani jednego klienta
 * ze zgodą. Komunikat jest przeznaczony dla SPRZEDAWCY (pokazywany w panelu),
 * więc mówi wprost, co zrobić dalej.
 */
class BulkMailException extends RuntimeException
{
}
