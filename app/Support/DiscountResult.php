<?php

namespace App\Support;

use App\Models\DiscountCode;

/**
 * Wynik sprawdzenia kodu rabatowego dla konkretnego koszyka: przyjęty (z kwotą
 * zniżki albo darmową wysyłką) albo odrzucony z powodem po polsku.
 *
 * Powód jest częścią wyniku, nie wyjątkiem, bo odrzucenie kodu to normalny bieg
 * rzeczy w koszyku — klient ma zobaczyć, DLACZEGO kod nie działa („kod działa od
 * zamówień za 200 zł"), a nie samo „nieprawidłowy kod".
 */
class DiscountResult
{
    private function __construct(
        public readonly ?DiscountCode $code,
        public readonly float $itemsDiscount,
        public readonly bool $freeShipping,
        public readonly ?string $error,
    ) {}

    public static function accept(DiscountCode $code, float $itemsDiscount, bool $freeShipping): self
    {
        return new self($code, round($itemsDiscount, 2), $freeShipping, null);
    }

    public static function reject(string $error): self
    {
        return new self(null, 0.0, false, $error);
    }

    public function accepted(): bool
    {
        return $this->error === null;
    }
}
