---
name: plan-order-statuses
description: "WDROŻONE 2026-07-14: statusy zamówień = ścieżki wg (płatność × dostawa); scenariusz wysyłki odłożony do modułu dostaw; anulowanie terminalne ze zwrotem na stan; mail przy KAŻDEJ zmianie."
metadata: 
  node_type: memory
  type: project
  originSessionId: bd2d4bfb-a65d-46ef-b991-378de6a45f4a
---

> **STAN: WDROŻONE w całości 2026-07-14** (506 testów zielonych). Kod: `App\Support\OrderFlow` (ścieżka jako funkcja płatność×dostawa), `App\Services\OrderStatusChanger` (JEDYNA droga zmiany statusu z panelu: reguły + zwrot na stan + mail — `Order::changeStatus()` zdegradowany do prymitywu, który nic z tego nie robi), `OrderMailer::statusChanged()` i `::cancelled()` (osobne, bo mail o anulowaniu nie może zapraszać po odbiór), `Order::countedAsSale()` (scope wykluczający anulowane z liczydeł), `OrderStatus::isTerminal()`, `Product::tracksStock()`. USUNIĘTE: martwy case `OrderStatus::Shipped` i `OrderStatus::transitionChoices()` (była to sama podpowiedź UI — dziś reguły są egzekwowane w backendzie). Poniżej ustalenia, które za tym stoją.

**Rafał rozpisał statusy 2026-07-14 i to jest wersja obowiązująca.** Nadpisuje „głęboką maszynę stanów" z [[vision-email-driven-orders]] w części „ścieżki statusów per scenariusz" — ta wizja zostaje aktualna tylko w częściach: auto-przejścia z płatności/kuriera i zarządzanie zamówieniem z maila.

**Zasada naczelna Rafała: „statusów ma być jak najmniej, prosto znaczy lepiej".**

## Trzy ścieżki = funkcja (metoda płatności × metoda dostawy)

1. **Gotówka + odbiór osobisty** (`PayOnPickup` + `Pickup`): Nowe → W realizacji → Gotowe do odbioru → Zrealizowane.
2. **Przelew (na konto / online) + odbiór osobisty** (`BankTransfer` + `Pickup`): Oczekuje na płatność → Opłacone → W realizacji → Gotowe do odbioru → Zrealizowane.
3. **Przelew + wysyłka** — **ODŁOŻONE** (patrz niżej): Oczekuje na płatność → Opłacone → W realizacji → Gotowe do wysyłki → Zrealizowane.

**Statusu „Nowe" NIE MA przy przelewie** (decyzja Rafała) — żyłby jedną sekundę, bo zaraz po złożeniu i tak oczekuje się na wpłatę. Status początkowy zależy więc od metody płatności. Licznik nowych zamówień w menu to osobna kolumna (`unseen_orders_count`), więc nie ucierpi.

**„Wysłane" NIE ISTNIEJE jako osobny status** — świadoma decyzja Rafała: „jeśli sklep zrealizował zlecenie, to znaczy, że musiał je wysłać". Nie będzie pary Wysłane → Zrealizowane. Konsekwencja: `OrderStatus::Shipped` to martwy case do usunięcia; scenariusz 3 dostanie zamiast niego `ReadyToShip` („Gotowe do wysyłki").

## Scenariusz 3 (wysyłka) — DOCELOWO, nie teraz

Odłożony świadomie (2026-07-14), bo `DeliveryMethod` ma dziś jeden case (`Pickup`) — wysyłki nie ma ani w kasie, ani w ustawieniach sklepu, ani w koszcie dostawy (zawsze 0). To nie jest „status do dopisania", tylko cały moduł dostaw. **Ścieżka jest zaprojektowana i ma zostać wdrożona razem z modułem dostaw** — patrz [[shipping-aggregator-idea]].

## Reguły przejść

- Statusy pokazywane **w kolejności ścieżki**; sugerowany jest następny.
- Statusy **spoza ścieżki danego zamówienia nie istnieją** dla niego (przy gotówce nie ma „Opłacone" — nie ma czego potwierdzać).
- Wolno wrócić do dowolnego statusu ze ścieżki, ale **każdy skok inny niż następny wymaga potwierdzenia** („czy na pewno") — zabezpieczenie przed pomyłką.
- **Mail do klienta przy KAŻDEJ zmianie statusu, bez wyjątków i bez opcji wyłączenia.** Rafał odrzucił mój pomysł ptaszka „powiadom klienta" przy cofaniu: „klient musi o tym wiedzieć, inaczej przyjdzie odebrać coś, czego nie ma". Upraszcza przekaz i UI. Mail zawiera całe zamówienie + nowy status + datę jego ustawienia + opcjonalną notatkę.

## Anulowanie

- **Terminalne i nieodwracalne.** Zamówienie zostaje w systemie, ale jest **informacyjne — wszystko w nim zablokowane** (także edycja pozycji). Z „Anulowane" nie ma wyjścia; pomyłkę naprawia się nowym zamówieniem. Powód techniczny: anulowanie oddaje towar na stan, więc cofnięcie musiałoby zdjąć go ponownie — a mogło go w międzyczasie zabraknąć.
- **Wymaga potwierdzenia** — modal w naszym stylu (nie `wire:confirm`), z **opcjonalnym polem „powód anulowania"** → ląduje w mailu do klienta i na osi czasu.
- **Mail do kupującego** o anulowaniu: wykaz produktów + kwota.
- **Zwrot na stan magazynowy** — tylko produkty z włączonym `track_stock`. Mechanika już istnieje (`OrderEditor::applyStockDelta()`), nie jest tylko podpięta.
- **Anulowane nie liczy się jako zakup w ŻADNYCH ilościach ani kwotach** (Rafał 2026-07-14) — nie tylko przychód, także liczniki zamówień i sztuk produktów. Zostaje w historii **wyłącznie informacyjnie**, jako ślad, że tak było: zamówienie mogło być opłacone i dopiero potem anulowane, więc nie wolno go wymazać z systemu (dlatego też lista zamówień nadal je pokazuje — tylko liczniki nad nią ich nie liczą). Gotcha: dziś wyklucza je TYLKO Pulpit (`DashboardController`); karta „Twoja sprzedaż" nad listą zamówień i „wydano łącznie" na koncie klienta liczą je normalnie. Brak wspólnego scope'a na `Order` — każde miejsce filtruje osobno.

**Why:** statusy to fundament całego działu zamówień — Rafał odkładał go tygodniami właśnie dlatego, że musi być domknięty przed budową kanału mailowego i auto-przejść. Ta wersja jest znacząco prostsza od pierwotnej wizji i to jest w niej celowe.

**How to apply:** ścieżka = funkcja `(PaymentMethod, DeliveryMethod)`, nie płaska lista; egzekwowana twardo, nie tylko w UI (dziś `OrderStatus::transitionChoices()` to sama podpowiedź — `changeTo()` przyjmuje dowolny case enuma). Uwaga na migrację danych: istniejące zamówienia z przelewem mają status `new`, którego ścieżka 2 nie zna. Element po elemencie z przystankiem na froncie ([[incremental-checkpoints-per-element]]). Powiązane: [[next-orders-panel-tab]], [[email-outbox-cron-pattern]], [[per-shop-email-identity-branding]], [[stock-availability-verification]], [[bank-transfer-payment-method]].
