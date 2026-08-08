<?php

namespace App\Enums;

/**
 * Sposób NADANIA przesyłki — czyli jak paczka wychodzi od sprzedawcy do InPostu.
 * Wartość enumu to `custom_attributes.sending_method` w ShipX.
 *
 * UWAGA na zrost pojęć: to NIE jest metoda dostawy ({@see DeliveryMethod}).
 * Klient w kasie wybiera, JAK MA DOSTAĆ paczkę (paczkomat albo pod adres);
 * sprzedawca decyduje osobno, JAK SIĘ JEJ POZBĘDZIE. Te dwie rzeczy są
 * niezależne — paczkę jadącą pod adres można wrzucić do paczkomatu, a paczkę
 * do paczkomatu może zabrać kurier spod drzwi sprzedawcy.
 *
 * DEKLARACJA JEST WIĄŻĄCA I NIEODWRACALNA (zweryfikowane na sandboxie
 * 2026-08-08). Przesyłka utworzona jako `parcel_locker` wchodzi w stan
 * `CustomerDelivering` i zlecenie odbioru na nią zostaje ODRZUCONE („status
 * should be Prepared, but is CustomerDelivering”) — przy czym odrzucenie
 * przychodzi asynchronicznie, samo utworzenie zlecenia zwraca 201. Zmiana po
 * nadaniu też odpada (`PUT /v1/shipments/{id}` → `shipment_status_incorrect`).
 * Dlatego sposób nadania musi być znany W CHWILI nadawania, a nie później.
 *
 * PaczkoPunkt (`pok`) świadomie pominięty: ShipX wymaga wtedy wskazania
 * konkretnego punktu (`dropoff_point`), czyli kolejnego pola i kolejnej mapy.
 * Dołożymy, jeśli ktokolwiek o to poprosi.
 */
enum SendingMethod: string
{
    case ParcelLocker = 'parcel_locker';
    case DispatchOrder = 'dispatch_order';

    /**
     * Domyślny sposób nadania — zawsze ten DARMOWY. Odbiór kuriera kosztuje,
     * więc nie może włączyć się sam ani „przy okazji”.
     */
    public static function default(): self
    {
        return self::ParcelLocker;
    }

    public function label(): string
    {
        return match ($this) {
            self::ParcelLocker => 'Wrzucam paczki do Paczkomatu',
            self::DispatchOrder => 'Odbierze je kurier InPost',
        };
    }

    /**
     * Co sprzedawca faktycznie robi — sama nazwa nie wystarcza, bo obie opcje
     * brzmią podobnie, a różnią się tym, kto się rusza.
     */
    public function hint(): string
    {
        return match ($this) {
            self::ParcelLocker => 'Etykietę drukujesz i wrzucasz paczkę do dowolnego paczkomatu nadawczego.',
            self::DispatchOrder => 'Kurier przyjeżdża po paczki pod Twój adres — zamawiasz jeden przyjazd na wszystkie naraz.',
        };
    }

    /**
     * Czy InPost pobiera za ten sposób nadania dodatkową opłatę. Musi być
     * widoczne w interfejsie: to pieniądze sprzedawcy, a wybór jest trwały.
     */
    public function isPaid(): bool
    {
        return $this === self::DispatchOrder;
    }
}
