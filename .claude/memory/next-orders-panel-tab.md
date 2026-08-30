---
name: next-orders-panel-tab
description: "Zakładka „Zamówienia\" w panelu sprzedawcy: DOMKNIĘTA 2026-07-14 — lista, szczegół, edycja, statusy wg ścieżek + maile + anulowanie."
metadata: 
  node_type: memory
  type: project
  originSessionId: d1e6721f-472a-4484-94aa-a199def0975a
---

Zakładka „Zamówienia" w panelu sprzedawcy — postęp:
- **Lista + szczegół (odczyt)** — ZROBIONE (commit `3048ddb`, patrz [[handoff-2026-07-03]] po kontekst modelu/kasy).
- **Zmiana statusu (2026-07-05)** — ZROBIONE. Komponent Livewire `App\Livewire\Seller\OrderStatusManager` w karcie „Status" na szczególe. Przejścia **wybaczające**: `OrderStatus::suggestedNext(DeliveryMethod)` podpowiada 2 kroki naprzód + „Anuluj" (rozwidlenie odbiór→ReadyForPickup / wysyłka→Shipped przez `DeliveryMethod::isShipped()`), a „inny status…" daje pełną listę. Terminalne (Completed/Cancelled) nie proponują nic (`isTerminal()`). Autoryzacja: `abort_unless` na shop_id w `changeTo`. **Oś czasu**: tabela `order_status_events` (from/to/note/created_at, niezmienna, kaskada), relacja `Order::statusEvents()`, jedyny mutator statusu = `Order::changeStatus()` (no-op gdy bez zmiany). Pierwsza linia osi = `orders.created_at` („Złożone"), bez backfillu starych. 362 testy.

> **NIEAKTUALNE OD 2026-07-14: dział statusów jest ZROBIONY** — Rafał przyszedł z własną, dużo prostszą rozpiską. Obowiązuje [[plan-order-statuses]] (ścieżki per płatność×dostawa, mail przy KAŻDEJ zmianie, anulowanie terminalne ze zwrotem na stan), przebieg: [[handoff-2026-07-14-statuses]]. Opis „przejść wybaczających" i `suggestedNext()` poniżej jest już nieprawdziwy — `transitionChoices()` usunięte, reguły egzekwuje backend. Zostaje aktualny tylko opis osi czasu i `order_status_events`.

**Głęboki dział statusów ODŁOŻONY na świeżą sesję (2026-07-05).** Rafał: temat „kiedy jaki status można ustawić" + „które ustawiają się automatycznie" + zarządzanie zamówieniem prosto z maila to za duży i za ważny dział, żeby robić na doczepkę. Pełna wizja: [[vision-email-driven-orders]] (maszyna stanów per scenariusz, auto-przejścia z płatności/kuriera, akcje z maila, InPost/Furgonetka z etykietami). Obecny stan (ręczna zmiana + oś czasu) to fundament, następna sesja go rozbuduje/przemodeluje.

Powiązany, mniejszy krok w kolejce: mail do klienta przy zmianie statusu (outbox [[email-outbox-cron-pattern]] + branding [[per-shop-email-identity-branding]], opt-in per status) — ale to część większego kanału mailowego z wizji.

**How to apply:** element po elemencie z przystankiem na froncie ([[incremental-checkpoints-per-element]]).
