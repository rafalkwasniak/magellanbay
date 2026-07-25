<?php

namespace App\Support;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\DiscountCode;

/**
 * Kod rabatowy opowiedziany po polsku — zdanie po zdaniu. Sprzedawca ma czytać,
 * co właśnie ustawił, zamiast odszyfrowywać pola formularza; to samo streszczenie
 * pokazujemy przy edycji, więc opis nie może rozjechać się z zapisem.
 *
 * Działa na modelu NIEZAPISANYM (formularz buduje sobie kod „na brudno" i pyta
 * o opis przy każdej zmianie pola), dlatego wszędzie zakładamy, że pole może być
 * jeszcze puste.
 */
class DiscountSummary
{
    /**
     * @return list<string>
     */
    public static function lines(DiscountCode $code): array
    {
        $name = filled($code->code) ? 'Kod '.$code->code : 'Ten kod';

        return array_values(array_filter([
            self::effect($code, $name),
            self::minimum($code),
            self::validity($code),
            self::uses($code),
            self::audience($code),
            self::shippingNote($code),
            self::switchedOff($code),
        ]));
    }

    private static function effect(DiscountCode $code, string $name): string
    {
        if ($code->type === DiscountType::FreeShipping) {
            return $name.' daje klientowi darmową wysyłkę.';
        }

        $amount = $code->discountLabel();

        if ($code->scope === DiscountScope::Product) {
            $product = $code->product?->name;

            return $product !== null
                ? $name.' obniża cenę produktu „'.$product.'" o '.$amount.'.'
                : $name.' obniży cenę wybranego produktu o '.$amount.' — wskaż jeszcze, którego.';
        }

        return $name.' obniża wartość produktów w koszyku o '.$amount.'.';
    }

    private static function minimum(DiscountCode $code): ?string
    {
        if ($code->min_items_total === null) {
            return null;
        }

        return 'Zadziała, gdy produkty w koszyku kosztują co najmniej '
            .Money::pln((float) $code->min_items_total).' — koszt wysyłki się do tego nie liczy.';
    }

    private static function validity(DiscountCode $code): string
    {
        if ($code->starts_at === null && $code->ends_at === null) {
            return 'Ważny bezterminowo.';
        }

        if ($code->starts_at === null) {
            return 'Ważny do '.$code->ends_at->format('j.m.Y').' (włącznie).';
        }

        if ($code->ends_at === null) {
            return 'Zadziała od '.$code->starts_at->format('j.m.Y').'.';
        }

        return 'Ważny od '.$code->starts_at->format('j.m.Y').' do '.$code->ends_at->format('j.m.Y').' (włącznie).';
    }

    private static function uses(DiscountCode $code): string
    {
        return match (true) {
            $code->max_uses === null => 'Bez limitu użyć.',
            $code->max_uses === 1 => 'Do użycia tylko raz.',
            default => 'Do użycia maksymalnie '.$code->max_uses.' razy.',
        };
    }

    private static function audience(DiscountCode $code): string
    {
        if (! $code->isPersonal()) {
            return 'Może go użyć każdy, kto go zna.';
        }

        $customer = $code->customer;
        $who = trim(($customer?->name ?? '').' '.($customer?->surname ?? ''));

        return 'Tylko dla klienta: '.($who !== '' ? $who : ($customer?->email ?? 'wskazanego')).'.';
    }

    /**
     * Zasada, którą powtarzamy przy każdym kodzie zniżkowym — bo to najczęstsze
     * nieporozumienie: rabat nigdy nie zjada kosztu dostawy.
     */
    private static function shippingNote(DiscountCode $code): ?string
    {
        return $code->type?->appliesToItems() ? 'Nie obejmuje kosztu wysyłki.' : null;
    }

    private static function switchedOff(DiscountCode $code): ?string
    {
        return $code->is_active ? null : 'Kod jest wyłączony — nie zadziała w koszyku, dopóki go nie włączysz.';
    }
}
