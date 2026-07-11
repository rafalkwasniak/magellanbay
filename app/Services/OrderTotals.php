<?php

namespace App\Services;

use App\Enums\VatRate;
use App\Models\Order;

/**
 * Jedno źródło przeliczania kwot zamówienia. Formuła jest „od brutto": netto i
 * VAT wyliczamy z ceny brutto i ułamka stawki, zaokrąglając per linia — dokładnie
 * tak, jak przy składaniu zamówienia. Dzięki temu zamówienie utworzone (z koszyka)
 * i zamówienie edytowane (panel sprzedawcy) liczą się identycznie.
 */
class OrderTotals
{
    /**
     * Przelicza pozycje i sumy zamówienia z bieżącej ceny/ilości pozycji i zapisuje.
     * Odświeża `line_total_gross` każdej pozycji (gdy drgnął) oraz `items_total`,
     * `total_net`, `total_vat`, `total_gross` zamówienia (brutto = produkty + dostawa).
     */
    public function recalculate(Order $order): void
    {
        $itemsTotal = 0.0;
        $totalNet = 0.0;
        $totalVat = 0.0;

        foreach ($order->items as $item) {
            [$lineGross, $lineNet, $lineVat] = $this->lineAmounts(
                (float) $item->unit_price_gross,
                (float) $item->quantity,
                $item->vat_rate,
            );

            if ((float) $item->line_total_gross !== $lineGross) {
                $item->line_total_gross = $lineGross;
                $item->save();
            }

            $itemsTotal += $lineGross;
            $totalNet += $lineNet;
            $totalVat += $lineVat;
        }

        $order->update([
            'items_total' => round($itemsTotal, 2),
            'total_net' => round($totalNet, 2),
            'total_vat' => round($totalVat, 2),
            'total_gross' => round($itemsTotal + (float) $order->delivery_cost, 2),
        ]);
    }

    /**
     * Wartości pojedynczej linii z ceny brutto i ilości: [brutto, netto, VAT].
     * Zaokrąglenie per linia, brutto pierwsze (netto = brutto / (1 + stawka)).
     *
     * @return array{float, float, float}
     */
    public function lineAmounts(float $unitGross, float $quantity, VatRate $vat): array
    {
        $lineGross = round($unitGross * $quantity, 2);
        $lineNet = round($lineGross / (1 + $vat->fraction()), 2);
        $lineVat = round($lineGross - $lineNet, 2);

        return [$lineGross, $lineNet, $lineVat];
    }
}
