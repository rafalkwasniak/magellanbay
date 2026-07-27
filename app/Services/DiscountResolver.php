<?php

namespace App\Services;

use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Shop;
use App\Support\DiscountResult;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Sprawdza kod rabatowy wpisany przez klienta i wylicza, ile zdejmuje z TEGO
 * koszyka. Jedno miejsce prawdy dla koszyka i dla kasy — kod przyklejony w
 * koszyku jest sprawdzany PONOWNIE przy składaniu zamówienia, bo między jednym
 * a drugim klient zmienia zawartość, a sprzedawca bywa szybszy i wyłącza kod.
 *
 * Rabat schodzi wyłącznie z wartości produktów; typ „darmowa wysyłka" nie tyka
 * produktów i sygnalizuje wołającemu, żeby wyzerował koszt dostawy.
 */
class DiscountResolver
{
    /**
     * @param  Collection<int, array{product: \App\Models\Product, quantity: float, unit_price: float, line_total: float}>  $lines
     */
    public function resolve(Shop $shop, string $code, Collection $lines, ?Customer $customer = null): DiscountResult
    {
        $code = mb_strtoupper(trim($code));

        if ($code === '') {
            return DiscountResult::reject('Wpisz kod rabatowy.');
        }

        if ($lines->isEmpty()) {
            return DiscountResult::reject('Dodaj coś do koszyka, zanim użyjesz kodu.');
        }

        /** @var DiscountCode|null $discount */
        $discount = $shop->discountCodes()->with('product')->where('code', $code)->first();

        if ($discount === null) {
            return DiscountResult::reject('Nie znamy takiego kodu.');
        }

        $status = $discount->status();

        if (! $status->isUsable()) {
            return DiscountResult::reject($this->statusReason($status));
        }

        if (! $discount->isUsableBy($customer)) {
            return DiscountResult::reject($customer === null
                ? 'Ten kod jest przypisany do konta klienta — zaloguj się, aby go użyć.'
                : 'Ten kod został wystawiony innemu klientowi.');
        }

        $itemsTotal = (float) $lines->sum('line_total');

        if (! $discount->meetsMinimum($itemsTotal)) {
            return DiscountResult::reject(
                'Ten kod działa od zamówień za '.Money::pln((float) $discount->min_items_total).' (bez kosztu wysyłki).',
            );
        }

        if ($discount->type->appliesToShipping()) {
            return DiscountResult::accept($discount, 0.0, true);
        }

        // Podstawa rabatu: cały koszyk albo wartość jednej pozycji.
        if ($discount->scope === DiscountScope::Product) {
            $line = $lines->firstWhere(fn (array $line) => $line['product']->id === $discount->product_id);

            if ($line === null) {
                return DiscountResult::reject(
                    'Ten kod dotyczy produktu „'.$discount->targetLabel().'", którego nie ma w koszyku.',
                );
            }

            return DiscountResult::accept($discount, $discount->discountOn((float) $line['line_total']), false);
        }

        return DiscountResult::accept($discount, $discount->discountOn($itemsTotal), false);
    }

    /**
     * Dlaczego kod nie działa — językiem klienta, nie nazwą stanu. Klient nie ma
     * wiedzieć, że sprzedawca „wyłączył" kod; ma wiedzieć, że nie zadziała.
     */
    private function statusReason(DiscountStatus $status): string
    {
        return match ($status) {
            DiscountStatus::Expired => 'Ten kod stracił ważność.',
            DiscountStatus::Scheduled => 'Ten kod jeszcze nie obowiązuje.',
            DiscountStatus::Exhausted => 'Ten kod został już wykorzystany.',
            default => 'Ten kod jest nieaktywny.',
        };
    }
}
