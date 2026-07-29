<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Rzucany przy rejestrowaniu zwrotu konsumenckiego (OrderReturnService), gdy
 * zgłoszenie jest niemożliwe: pusty wybór, ilość ponad to, co zostało do
 * zwrotu, pozycja z wyjątku art. 38, zamówienie anulowane. Komunikat jest
 * przeznaczony dla KLIENTA (pokazywany na publicznym formularzu zwrotu), więc
 * pisany jego językiem — bez żargonu i bez szczegółów technicznych.
 */
class OrderReturnException extends RuntimeException
{
}
