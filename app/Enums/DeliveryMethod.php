<?php

namespace App\Enums;

/**
 * Metody dostawy (spec „Dostawy"). Odbiór osobisty, kurier „pod adres",
 * paczkomat InPost oraz pobraniowe warianty obu wysyłek. Kolejne (np. dostawa
 * własna) dojdą jako nowe case'y + konfiguracja per-sklep.
 *
 * DLACZEGO POBRANIE JEST DOSTAWĄ, A NIE PŁATNOŚCIĄ (decyzja Rafała 17.08):
 * sprzedawca ustala dla niego OSOBNĄ CENĘ i włącza je OSOBNYM przełącznikiem —
 * dokładnie tak, jak dla kuriera i paczkomatu. Gdyby pobranie było metodą
 * płatności, cennik i włącznik trzeba by budować drugi raz, w innym miejscu i
 * innym językiem. Zamówienie i tak dostaje własną metodę płatności
 * ({@see PaymentMethod::CashOnDelivery}), ale klient jej nie wybiera — wynika
 * z dostawy.
 *
 * Pobranie NIE działa bez konta InPost (to InPost inkasuje pieniądze i przelewa
 * je sprzedawcy), inaczej niż zwykły kurier, który bywa dostawą własną za 0 zł.
 * Warunek egzekwuje Shop::courierCodAvailable() / parcelLockerCodAvailable().
 */
enum DeliveryMethod: string
{
    case Pickup = 'pickup';
    case Courier = 'courier';
    case ParcelLocker = 'parcel_locker';
    case CourierCod = 'courier_cod';
    case ParcelLockerCod = 'parcel_locker_cod';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Odbiór osobisty',
            self::Courier => 'Kurier',
            self::ParcelLocker => 'Paczkomat InPost',
            // Etykiety pobraniowych są DOKLEJKĄ do metod bazowych („Kurier" →
            // „Kurier za pobraniem"), a nie osobnymi nazwami. Klient widzi obie
            // pozycje obok siebie w jednej liście, więc muszą się rymować —
            // inaczej wyglądają jak dwie niezwiązane usługi.
            self::CourierCod => 'Kurier za pobraniem',
            self::ParcelLockerCod => 'Paczkomat InPost za pobraniem',
        };
    }

    /**
     * Czy klient płaci dopiero przy odbiorze, a pieniądze inkasuje InPost.
     * Rozstrzyga o trzech rzeczach naraz: metodzie płatności zamówienia,
     * dorzuceniu `cod`/`insurance` do przesyłki oraz zamku na edycję po nadaniu
     * (kwoty pobrania NIE DA SIĘ już zmienić — zweryfikowane na sandboxie
     * 17.08: `PUT` na przesyłce w stanie `confirmed` zwraca 400
     * `shipment_status_incorrect`, anulowanie przesyłki również).
     */
    public function isCashOnDelivery(): bool
    {
        return match ($this) {
            self::Pickup, self::Courier, self::ParcelLocker => false,
            self::CourierCod, self::ParcelLockerCod => true,
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
            self::Courier, self::ParcelLocker, self::CourierCod, self::ParcelLockerCod => true,
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
            self::Pickup, self::ParcelLocker, self::ParcelLockerCod => false,
            self::Courier, self::CourierCod => true,
        };
    }

    /**
     * Czy metoda potrzebuje WSKAZANIA PUNKTU (kodu paczkomatu). Klient podaje
     * kod z palca albo — docelowo — wybiera go na mapie (geowidget). Mapa jest
     * nakładką na to pole, nie warunkiem metody.
     */
    public function requiresParcelLocker(): bool
    {
        return $this === self::ParcelLocker || $this === self::ParcelLockerCod;
    }
}
