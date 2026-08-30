---
name: plan-sale-unit-weight
description: "WDROŻONE 2026-07-05 — sprzedaż na sztuki vs kilogramy; ilość integer→decimal(10,2); jednostka per produkt + domyślna sklepu; enum SaleUnit; koszyk krok 0,5 kg + wpisywanie z palca. 350 testów."
metadata: 
  node_type: memory
  type: project
  originSessionId: 41e24f90-4877-4fc6-a8e2-a668d3d40eda
---

**Cel biznesowy:** otworzyć nowy segment klientów — warzywniaki, wędliniarze itp. Produkt można sprzedawać **na sztuki** (jak teraz) albo **na wagę (kg)**. Rafał ma realnych klientów czekających na to.

## Werdykt (moja analiza kodu, 2026-07-05)
Zmiana **szeroka, ale płytka** — ~10 plików, w każdym po trochu; **NIE przepisujemy architektury**. Sedno: **ilość przestaje być liczbą całkowitą, staje się dziesiętną**. **Cena zostaje bez zmian** — nadal „za 1 jednostkę" (1 kg albo 1 szt.). To właśnie czyni to tanim. Szacunek: **1–2 skupione sesje** (~⅓ mechaniczne poszerzenie typów, ~⅔ UX koszyka + formatowanie + testy groszy). Ryzyko regresji niskie: `piece` = domyślna ścieżka → integer to po prostu decimal `.00`, istniejące zachowanie bez zmian. `order_items` to migawka → **historyczne zamówienia nietknięte**.

**Timing USTALONY:** robimy **PRZED** panelem Zamówień (żeby Zamówienia od razu umiały pokazać „2,50 kg"). Minimum: model danych (typy + `sale_unit`) przed Zamówieniami; UX koszyka może przyjść tuż po.

## Decyzje (USTALONE z Rafałem 2026-07-05)
1. **Jednostka per PRODUKT + domyślna jednostka sklepu** — dokładnie wzorzec VAT ([[plan-shop-settings-storage]]): `shops.default_sale_unit` prefilluje formularz produktu, a produkt może **nadpisać**. (Rafał chciał najpierw per-sklep; przekonał go argument, że warzywniak sprzedaje ziemniaki na kg, a jajka/pęczki na szt. — per-sklep zamknąłby pół asortymentu. Zaakceptował, że trzeba to pogodzić na listingach/w kasie/zamówieniu.)
2. **Ilość zawsze wpisywalna „z palca"** — nawet przy sztukach (pole liczbowe, nie tylko +/−). Dla **kg krok +/− = 0,5 kg**, ale dokładną wagę można wpisać ręcznie (np. 1,20 kg). Typowo ludzie biorą 2 kg / 3 kg, rzadziej 1,2 kg — ale opcja ma być.
3. **Precyzja: 2 miejsca po przecinku** — `decimal(_,2)`, granulacja 10 g. Bez kombinowania z gramami/3 miejscami.

## Stan obecny — ilość jest INTEGEREM w całym łańcuchu (co ruszyć)
Zweryfikowane w kodzie 2026-07-05:
- `products.stock` = `unsignedInteger` nullable; `order_items.quantity` = `unsignedInteger`. → oba na `decimal(10,2)` (integer mieści się → bez utraty danych, wstecznie zgodne).
- `Product` casts: `stock => integer` → `decimal:2`. Dodać `products.sale_unit` (enum) + `shops.default_sale_unit`.
- [CartService](app/Services/CartService.php): typy `int` wszędzie (`raw`, `add`, `setQuantity`, `capToStock`, `overwrite`), licznik `count()`=`array_sum`. → `float`.
- [Cart.php](app/Livewire/Cart.php): stepper `increment`/`decrement` o 1 → krok zależny od jednostki (0,5 kg / 1 szt.) + pole ręczne.
- [AddToCart.php](app/Livewire/AddToCart.php): `public int`, `add(1)`.
- [OrderService](app/Services/OrderService.php): `min($qty,$stock)`, `decrement('stock',$qty)`, `round($unit*$qty,2)` (round już jest — zostaje).
- [ProductRequest](app/Http/Requests/Seller/ProductRequest.php): `stock => integer` → `numeric` + krok/precyzja.
- Widoki z twardym „szt.": [cart.blade](resources/views/livewire/cart.blade.php) („/ szt.", „maks. N szt."), [add-to-cart.blade](resources/views/livewire/add-to-cart.blade.php) („Dostępne: N szt."), [checkout.blade](resources/views/livewire/checkout.blade.php) („N× nazwa"), [seller/products/index](resources/views/seller/products/index.blade.php) („Zostało N szt."), maile ([OrderMailer](app/Services/OrderMailer.php)).

## Do zbudowania
- **Warstwa formatowania jednostki = jedno źródło prawdy** (np. `App\Support\UnitFormat` albo metody na enumie `SaleUnit`): `qty($product, $x)` → „2,50 kg" / „3 szt."; `perUnit()` → „/kg" / „/szt.". Użyć w koszyku, kasie, mailach, karcie produktu, listingu panelu. Enum `SaleUnit` (piece|weight) — nazwy PL widoczne, wartość EN stała ([[naming-and-locale-convention]]).
- **Koszyk UX** (największy kawałek): krok +/− zależny od jednostki (0,5 kg / 1 szt.) + pole wpisywane. Normalizacja wpisu w duchu [[input-normalization-conventions]].

## Poddecyzje — ROZSTRZYGNIĘTE 2026-07-05 (z Rafałem)
- **Licznik w nagłówku** = **liczba POZYCJI** (nie suma ilości) — `CartService::count()` = `count($raw)`. Nie miesza szt. z kg, nigdy ułamek.
- **Minimum dla wagi** = **krok 0,5 kg jako podłoga** (`SaleUnit::minQuantity()`). Powyżej dowolna waga z palca (2 miejsca). Wpis poniżej minimum → normalizeQuantity zwraca 0 → usunięcie pozycji.
- **Zaokrąglenia groszy** — `round($unit*$qty,2)` w CartService::lines i OrderService; pokryte testami (1,20 kg × 20 zł = 24,00).

## Jak zbudowane (mapa dla przyszłych sesji)
- **Enum `App\Enums\SaleUnit`** (piece|weight): label/abbreviation/perUnit/step/minQuantity/isWeight/normalizeQuantity/formatAmount/formatQuantity/inputAmount. JEDNO źródło formatowania — używane w koszyku, kasie, mailach, listingu, storefroncie, formularzu.
- **Migracje**: `products.stock` int→decimal(10,2) + `products.sale_unit`; `order_items.quantity` int→decimal(10,2) + `order_items.sale_unit` (zamrożona migawka); `shops.default_sale_unit`. Wszystkie default 'piece'.
- **Casty**: Product/Shop/OrderItem (`sale_unit`=>SaleUnit; `stock`/`quantity`=>decimal:2). Fillable rozszerzone.
- **CartService** cały na float; `add(?float=step)`, `setQuantity` normalizuje przez jednostkę.
- **Formularz produktu**: select „Jednostka sprzedaży" (prefill z `defaultSaleUnit`), przyrostki „szt./kg" przy cenie i stanie (JS z `data-units` renderowanego z enuma), stan sztuk zaokrąglany do całości w `ProductController::data()`.
- **Ustawienia sklepu**: select „Domyślna jednostka" obok VAT; `ShopSettingsRequest::prepareForValidation` uzupełnia brak wartością sklepu (nie wywraca starszych POST-ów).
- **Koszyk (Livewire Cart)**: `increment/decrement` o `step()`, `updateQuantity(id, value)` parsuje PL zapis; pole edytowalne `x-on:change="$wire.updateQuantity(...)"` z `wire:key` zależnym od ilości; kosz przy minimum, „−" wyżej.
- **Storefront**: cena z „/kg" tylko dla wagi (główna/listing/karta); sztuki bez zmian wizualnych.

Powiązane: [[plan-shop-settings-storage]], [[stock-availability-verification]], [[next-orders-panel-tab]], [[input-normalization-conventions]], [[naming-and-locale-convention]], [[frontend-stack-decision]].
