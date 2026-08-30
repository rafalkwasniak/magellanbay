---
name: stock-availability-verification
description: "WYKONANE 2026-07-20 — dostępność produktów domknięta na wszystkich etapach: atomowe zdjęcie ze stanu przy składaniu + komunikat korekty przy wyświetlaniu koszyka. Historia dla kontekstu."
metadata: 
  node_type: memory
  type: project
  originSessionId: 5012e4a9-f72a-42fb-a86c-3d1f251b9888
  modified: 2026-07-20T20:23:58.036Z
---

**WYKONANE 2026-07-20.** Cały wymóg „weryfikacja dostępności" domknięty i pokryty testami. Historia poniżej.

**Model (bez zmian, słuszny):** koszyk **nie rezerwuje** stanu. `product.stock` w koszyku = miękki limit/podpowiedź. Autorytatywne, atomowe zdjęcie ze stanu i rozstrzygnięcie „kto zdążył" dzieje się przy **składaniu zamówienia**.

**✅ Checkout — atomowe zdjęcie + kolizje** (`OrderService::place`): produkty blokowane `lockForUpdate()` w transakcji (nikt równolegle nie zmieni stanu), uzgodnienie koszyka → przy driftcie zapis uzgodnionej wersji + `CartNeedsReviewException` z komunikatami, potem atomowy `decrement('stock')` w tej samej transakcji. To był TODO „przy budowie checkoutu" — okazało się już zrobione przy budowie zamówień.

**✅ Auto-korekta koszyka** (`CartService`): dawniej `lines()` przycinał do stanu i wyrzucał nieaktywne/wyprzedane po cichu. 2026-07-20 zrefaktor: **`reconcile($shopId)` zwraca `['lines' => ..., 'notices' => [...]]`**; `lines()` = cienki wrapper (dla `total()` i `Checkout.php`, które nie potrzebują komunikatów). Treść komunikatów IDENTYCZNA jak w OrderService (jeden głos na obu etapach): „Ilość „X" dostosowana do dostępności (…)", „Produkt „X" wyprzedany…", „Jeden lub więcej produktów nie jest już dostępnych…".

**✅ Komunikat przy WYŚWIETLANIU koszyka** (była to ostatnia luka): `Cart::render()` woła `reconcile()`, przekazuje `notices` do `livewire.cart`; baner (`st-card st-border`, `role=status aria-live=polite`) nad listą — pokazuje się też, gdy wszystkie pozycje wypadły (nad rozgałęzieniem pusty/pełny). Baner znika przy kolejnej interakcji, bo sesja jest już uzgodniona (poprawny UX: „powiedz raz, gdy wykryto drift").

**Testy:** `CartServiceTest` (korekta ilości / usunięty / wyprzedany / brak zmian = brak notice), `CartLivewireTest` (baner jest / baner znika). Pełny pakiet koszyka/checkoutu (CartService+CartLivewire+Checkout+OrderPlacement) zielony.

Powiązane: [[frontend-stack-decision]], moduł koszyka (AddToCart/CartCounter/Cart, CartService).
