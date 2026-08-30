---
name: omnibus-lowest-price-30d
description: "WDROŻONE — historia cen + „najniższa cena z 30 dni\" (Omnibus) na kafelku produktu; bez pojęcia promocji."
metadata: 
  node_type: memory
  type: project
  originSessionId: 496eb5d0-33a2-486c-9a6d-89ca05143142
---

Obowiązek Omnibus („najniższa cena z 30 dni przed obniżką") wdrożony 2026-06-30.

**Decyzja produktowa (Rafał):** „cena to cena" — NIE wprowadzamy promocji/wyprzedaży/przeceny ani pola ceny regularnej vs promocyjnej. Zostaje czysty obowiązek informacyjny. To upraszcza całość: brak mechaniki obniżek.

**Jak działa:**
- Tabela `product_price_history` (`product_id`, `price_gross`, `recorded_at`), append-only, bez timestamps. Model `App\Models\ProductPriceHistory`.
- `ProductObserver::created` zapisuje cenę początkową; `::updated` zapisuje nowy wpis tylko gdy `wasChanged('price_gross')`. Migracja zaszczepia istniejące produkty wpisem od `created_at` (historii nie da się odtworzyć wstecz — dlatego zbieramy od dziś, niezależnie od storefrontu).
- `App\Support\OmnibusPrice::lowestBeforeCurrent()` = najniższa cena obowiązująca w oknie [now−30 dni, wejście bieżącej ceny); własny okres bieżącej ceny WYKLUCZONY. `Product::lowestPriceLast30Days()` zwraca tę wartość tylko gdy > cena bieżąca.
- **Reguła wyświetlania:** linijka „Najniższa cena z 30 dni: X zł" pokazuje się TYLKO przy realnej obniżce w ostatnich 30 dniach i sama znika, gdy nowa cena obowiązuje >30 dni (okno przed nią puste). Normalnie linijki nie ma → zero szumu, zgodne z „cena to cena". Na kafelku listy produktów ([seller/products/index.blade.php]).

**Dla storefrontu (później):** logika gotowa do ponownego użycia — wystarczy `lowestPriceLast30Days()` na karcie produktu. Eager-load `priceHistory`, by uniknąć N+1 (jak w `ProductController::index`).

Test: `tests/Feature/OmnibusPriceTest.php` (8 przypadków: zapis przy create/zmianie, brak zapisu przy zmianie innego pola, okno 30 dni, podwyżka, świeży produkt). Powiązane: [[shop-visibility-auto-publish]] (ten sam ProductObserver).
