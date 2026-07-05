<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * Zamówienia sklepu w panelu sprzedawcy. Wszystko scope'owane do sklepu
 * zalogowanego sprzedawcy; sprzedawca widzi wyłącznie własne zamówienia.
 * Dane zamówienia to migawka z chwili złożenia (OrderService) — tu tylko
 * je pokazujemy i zmieniamy status.
 */
class OrderController extends Controller
{
    public function index(Request $request): Renderable
    {
        $shop = $request->user()->shop;

        return view('seller.orders.index', [
            'shop' => $shop,
            'orders' => $shop
                ? $shop->orders()
                    ->withCount('items')
                    ->latest()
                    ->paginate(15)
                    ->withQueryString()
                : null,
        ]);
    }

    public function show(Request $request, Order $order): Renderable
    {
        $this->authorizeOrder($request, $order);

        return view('seller.orders.show', [
            'order' => $order->load('items'),
        ]);
    }

    /**
     * Tylko własne zamówienia sklepu zalogowanego sprzedawcy.
     */
    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->shop_id === $request->user()->shop?->id, 403);
    }
}
