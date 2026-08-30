---
name: shop-visibility-auto-publish
description: "WDROŻONE (2026-06-30) — widoczność sklepu = status, napędzany WYŁĄCZNIE aktywnymi produktami; auto-publikacja w obie strony przez ProductObserver."
metadata: 
  node_type: memory
  type: project
  originSessionId: 777df83f-73fe-4104-ad17-c20175e935a7
---

**WDROŻONE 2026-06-30.** Auto-publikacja sklepu. Decyzja Rafała: **widoczność sklepu zależy wyłącznie od produktów** — adres/NIP/opis/logo są opcjonalne („może mieć, nie musi”; ktoś bez firmy nie poda NIP-u, ktoś chce podejrzeć sklep bez logo).

Model (jedno źródło prawdy, bez nowej kolumny — przejęliśmy istniejący `status`):
- **`status` = publiczna widoczność**, nie osobny „cykl życia”. `Draft`/`Szkic` = brak aktywnych produktów (ukryty); `Active`/`Aktywny` = ≥1 aktywny produkt (widoczny). To rozwiało wątpliwość „czemu draft skoro widoczny” — status I widoczność to teraz to samo.
- **Stan zapisany w kolumnie** (admin i storefront czytają gotowe `status`, bez liczenia produktów per sklep), ale **utrzymywany automatycznie**.
- `App\Observers\ProductObserver` (wpięty atrybutem `#[ObservedBy]` na `Product`) na `saved`/`deleted`/`restored`/`forceDeleted` woła `Shop::refreshVisibility()`, które ustawia status z `hasActiveProducts()` (`products()->where('is_active',true)->exists()`). Zapis tylko gdy status faktycznie się zmienia.
- **Działa w obie strony:** 1. aktywny produkt → sklep Aktywny; usunięcie/wyłączenie ostatniego aktywnego → Szkic; przywrócenie/ponowna aktywacja → znów Aktywny.
- „Aktywny produkt” = flaga `is_active`. **Stan magazynowy 0 NIE ukrywa sklepu** (to sprawa pojedynczego produktu). Soft-delete produktu nie liczy się (relacja `products()` pomija usunięte).
- Helper `Shop::isVisible()` (= status Active) jako API dla pulpitu/storefrontu (storefront jeszcze nie istnieje — [[front-blocked-on-subdomain-ssl]]).
- **Przyszłe zawieszenie przez admina** = osobna, prostopadła flaga (`widoczny = ma produkty I nie zawieszony`), dokładamy gdy będzie potrzebne — NIE ten enum.

Copy pulpitu już to obiecywało („Opublikujemy automatycznie po dodaniu pierwszego produktu”) — teraz obietnica jest prawdziwa, bez zmian w widoku. Testy: `tests/Feature/Seller/ShopVisibilityTest.php` (7), całość 150 zielonych.

Powiązane: [[handoff-2026-06-29]], [[plan-packages]].
