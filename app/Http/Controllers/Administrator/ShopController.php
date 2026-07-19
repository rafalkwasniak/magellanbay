<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * Konsola admina — sklepy. Lista wszystkich sklepów platformy z pakietem,
 * ceną i stanem, jako wejście do ręcznego zarządzania uprawnieniami/ceną
 * per sklep (edytor — osobny komponent). Widok tylko dla roli admin
 * (middleware `role:admin` na grupie tras).
 */
class ShopController extends Controller
{
    public function index(Request $request): Renderable
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'package' => (string) $request->query('package', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $shops = Shop::query()
            ->with('owner')
            ->withCount('products')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhereHas('owner', fn ($o) => $o
                            ->where('name', 'like', $term)
                            ->orWhere('surname', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            })
            ->when($filters['package'] !== '', fn ($q) => $q->where('package', $filters['package']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('administrator.shops.index', [
            'shops' => $shops,
            'filters' => $filters,
            'packages' => config('shop.packages'),
            'statuses' => ShopStatus::cases(),
        ]);
    }

    public function edit(Shop $shop): Renderable
    {
        return view('administrator.shops.edit', ['shop' => $shop->load('owner')]);
    }
}
