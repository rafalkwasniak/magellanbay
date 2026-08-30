---
name: plan-new-orders-badge
description: "WDROŻONE 2026-07-10 — badge „nowe zamówienia\" jako licznik-powiadomienie na sklepie; +1 przy zamówieniu, zero przy wejściu na listę; badge w menu (9+) + kafelek na Pulpicie."
metadata: 
  node_type: memory
  type: project
  originSessionId: e90b3a77-ffb7-4acd-9882-84e462a7ff23
---

Badge „nowe zamówienia" przy zakładce Zamówienia + kafelek na Pulpicie. **WDROŻONE 2026-07-10.**

**Jak zrobione (pliki):** kolumna `unseen_orders_count` (unsignedInteger default 0) na `shops` — migracja `2026_07_10_120000_...`, cast `integer` w [[Shop]] (poza `$fillable`, jak `last_order_number`). `+1` atomowo `$shop->increment(...)` w `OrderService::place()` wewnątrz transakcji (tuż po `createOrder`). Zerowanie w `Seller\OrderController::index()` (`forceFill([...=>0])->save()` tylko gdy >0; ta sama instancja co nawigacja → badge gaśnie od razu na tej stronie). Badge w menu: klucz `'badge'` na pozycji „Zamówienia" w `$nav` (`components/layouts/panel.blade.php`), render w `components/panel-nav-items.blade.php` (kropka emerald-500, dwucyfrowe w całości, skrót `99+` dopiero powyżej 99 — Rafał: „16" niesie więcej niż „9+" przy tej samej szerokości) — jedna tablica $nav = desktop+mobile naraz. Kafelek Pulpitu: `unseenOrders` z `DashboardController`, siatka „Twoja sprzedaż" na `lg:grid-cols-4`, klikalny kafel `🔔` z akcentem emerald gdy >0 (→ lista). Kolor zielony celowo (Rafał: zielony = cieszymy się, że wpadło zamówienie), nie czerwony-alarm. Test: `tests/Feature/Seller/NewOrdersBadgeTest.php` (4 przypadki: +1, reset, widoczność, 9+). 384 testy zielone. Wymagał `npm run build` (nowe klasy rose/grid-cols-4).

Model = **powiadomienie „coś tu wpadło, odkąd nie zaglądałeś"**, NIE licznik zadań-do-zrobienia. Świadomie odrzucone: liczenie po statusie (bo „nowe / do opłacenia / opłacone" to wszystko świeże zamówienia, a wejście/wyjście nie ma zależeć od ruszenia statusu) oraz znacznik czasu / stan w sesji (sesja się gubi, każda podstrona musiałaby liczyć).

**Kształt (Rafała pomysł, dopieszczony):**
- Kolumna `unseen_orders_count` na **`shops`** (nie na userze — zamówienie należy do sklepu przez `shop_id`, panel jest scope'owany do sklepu). Default **0**.
- **+1** przy tworzeniu zamówienia (`increment('unseen_orders_count')`) — wepnąć w obserwator / `OrderService`, gdzie zamówienie powstaje.
- **Zero** przy wejściu na **listę** Zamówień (nie na sam panel) — w kontrolerze listy.
- Badge = liczba > 0 przy zakładce w menu; miły detal `9+` powyżej dziewięciu.
- **Pulpit:** ten sam licznik jako kafelek „Nowe zamówienia" (jest już na to miejsce w siatce; klik → lista). Jedno źródło, dwa miejsca.

**Dlaczego licznik bije COUNT po statusie:** odczyt badge jest DARMOWY (kolumna na już-załadowanym obiekcie sklepu → render nawigacji bez zapytania; znika obawa „każda podstrona musi liczyć"). Zapis rzadki (raz na zamówienie), nie na każdym renderze. Koszt odwrócony w dobrą stronę.

Akceptowany kompromis: licznik może zdryfować (np. zamówienie anulowane od razu policzy się jako +1) — to szturchnięcie-powiadomienie, nie księgowość; reset i tak czyści.

Powiązane: [[next-orders-panel-tab]], [[dashboard-stats-direction]].
