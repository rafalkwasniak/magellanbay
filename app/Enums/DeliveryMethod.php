<?php

namespace App\Enums;

/**
 * Metody dostawy (spec „Dostawy"). Odbiór osobisty, kurier „pod adres",
 * paczkomat InPost. Kolejne (Furgonetka, dostawa własna) dojdą jako nowe case'y
 * + konfiguracja per-sklep.
 */
enum DeliveryMethod: string
{
    case Pickup = 'pickup';
    case Courier = 'courier';
    case ParcelLocker = 'parcel_locker';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Odbiór osobisty',
            self::Courier => 'Kurier',
            self::ParcelLocker => 'Paczkomat InPost',
        };
    }

    /**
     * Czy metoda wiąże się z WYSYŁKĄ (a więc i z kosztem dostawy). Odbiór
     * osobisty — nie: nie ma czego dowozić, więc w podsumowaniu nie pokazujemy
     * wiersza „Dostawa" (a „gratis" ma sens dopiero przy wysyłce z progiem
     * darmowej dostawy).
     *
     * UWAGA: to NIE znaczy „potrzebny adres klienta" — paczkomat jest wysyłką
     * bez adresu. Do adresu jest requiresShippingAddress().
     */
    public function isShipped(): bool
    {
        return match ($this) {
            self::Pickup => false,
            self::Courier, self::ParcelLocker => true,
        };
    }

    /**
     * Czy metoda potrzebuje ADRESU klienta. Do niedawna pokrywało się z
     * isShipped(), bo jedyną wysyłką był kurier — paczkomat to rozerwał:
     * paczka jedzie do skrytki, nie pod dom, więc ulica i miasto kupującego
     * nie mają tu żadnej roli. Trzymamy osobno, żeby kasa nie żądała adresu
     * do paczkomatu, a OrderService nie zapisywał go „na wszelki wypadek".
     */
    public function requiresShippingAddress(): bool
    {
        return match ($this) {
            self::Pickup, self::ParcelLocker => false,
            self::Courier => true,
        };
    }

    /**
     * Czy metoda potrzebuje WSKAZANIA PUNKTU (kodu paczkomatu). Klient podaje
     * kod z palca albo — docelowo — wybiera go na mapie (geowidget). Mapa jest
     * nakładką na to pole, nie warunkiem metody.
     */
    public function requiresParcelLocker(): bool
    {
        return $this === self::ParcelLocker;
    }
}
