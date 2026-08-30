---
name: plan-admin-panel-and-landing
description: "DWA DUŻE BRAKI (uświadomione 2026-07-17 przez Rafała, pamięć o nich MILCZAŁA) — panel administratora praktycznie nie istnieje (1 trasa, 17-linijkowy kontroler), strona główna kramio.pl to placeholder. Panel admina odblokowuje pakiety."
metadata: 
  node_type: memory
  type: project
  originSessionId: d742d9f9-2a68-4405-a0fd-fa8cdd4f1c1d
  modified: 2026-08-10T18:58:55.065Z
---

**Rafał, 2026-07-17: „caly panel administratora jest do zrobienia i strona glowna dla kramio.pl tez".** Podsumowywałem zaległości z pamięci i oba wypadły — **pamięć nie miała o nich ANI JEDNEJ notatki**, mimo że to dwa z największych pozostałych kawałków. Lekcja: streszczając zaległości, sprawdzać kod, nie tylko pamięć.

## Panel administratora — stan zweryfikowany w kodzie 2026-07-17
Praktycznie **stub**. Cały panel to:
- `routes/web.php`: grupa `['auth','role:admin']`, prefix `administrator`, name `administrator.` — **dwie trasy**: `/panel` → `App\Http\Controllers\Administrator\DashboardController` (**17 linii**, liczy sprzedawców jednym `User::where('role', Seller)->count()`) oraz `/podglad-maila/{template}` (podgląd szablonów maili, dla nas).
- `resources/views/administrator/dashboard.blade.php` — **46 linii** („Najnowsze sklepy", „Pierwsze kroki").
- **Nie ma katalogów** `app/Livewire/Admin` ani `app/Http/Controllers/Admin` (jest tylko `Administrator/`).

## ✅ Dział „Sprzedawcy" WDROŻONY 2026-08-10 (commit `8f58e3a`)
Pierwszy z czterech martwych stubów żyje: `/administrator/sprzedawcy` (lista z filtrami i licznikiem zasięgu wysyłki) + karta konta z dowodem zgód + ponowna aktywacja + `users.last_login_at`. Szczegóły i gotchy: [[handoff-2026-08-10]].

**Panel admina ma dziś trzy realne działy: Pulpit, Sklepy, Sprzedawcy. Stubami zostają: Zamówienia, Pakiety, Ustawienia.**

**Czego brak:** zamówień przekrojowych. ~~zarządzania użytkownikami~~ — ZROBIONE 2026-08-10 (wyżej). ~~edytor uprawnień per sklep~~ i ~~zarządzanie sklepami~~ — **ZROBIONE 2026-07-19 (Faza 2, commit 0574856)**: konsola sklepów (lista `/administrator/sklepy` + edytor Livewire `Administrator\ShopManager`) ustawia per sklep pakiet/uprawnienia/cenę/`subscription_ends_at`/`comped`. Szczegóły w [[plan-packages]] (sekcja „Faza 2 GOTOWA").

**KLUCZOWE POWIĄZANIE (już domknięte):** ten edytor to „konsola" Rafała z [[plan-packages]] (znajomy-tester = snapshot Free + ręcznie podbite + comped; deal per klient). `subscription_ends_at` i `comped` mają już czym być ustawiane. **Pakiety odblokowane — można ręcznie sprzedawać** (egzekwowanie bramek = Faza 3). Zostaje: pulpit admina (realne liczby zamiast „0 sklepów"), zarządzanie użytkownikami/zamówieniami.

## Strona główna kramio.pl
Placeholder wyglądający na gotowy (209 linii, `welcome.blade.php`) — szczegóły i PILNY fałsz („1 200+ aktywnych sklepów") w [[open-landing-fabricated-stats]]. Redesign + prawdziwe treści = osobny duży temat.

## Poprawiona mapa zaległości (2026-07-17), wg ciężaru
1. **Panel administratora** (od zera; odblokowuje pakiety)
2. **Płatności online**
3. **Strona główna kramio.pl** (redesign)
4. **Wysyłki** — Furgonetka + etykiety ShipX + paczkomat z mapą ([[plan-shipping]])
5. **Własna analityka** ([[dashboard-stats-direction]], zakres otwarty)
6. **Korespondencja seryjna** ([[plan-bulk-mail]]), **kody rabatowe**
7. [[vision-email-driven-orders]]
Poza kodem: [[open-hosting-process-limit]] (Rafał odłożył).

## Notatki, które się zdezaktualizowały (sprostowane 2026-07-17)
- **Breadcrumbs ZROBIONE** — jest `resources/views/components/storefront/breadcrumbs.blade.php`; [[plan-storefront-theming]] wciąż mówi „TODO potwierdzone".
- **Testy pakietów ISTNIEJĄ** — `tests/Feature/Seller/PackageEntitlementsTest.php`; [[plan-packages]] mówi „brak jakichkolwiek testów".
- **`entitlement('invoices')` jest EGZEKWOWANE** (IntegrationController, Order, OrderInvoice, Shop).

## ~~Znaleziona dziura: `courier_shipping` to martwy klucz~~ — ZAMKNIĘTE (sprawdzone w kodzie 2026-08-04)
Notatka z 17.07 mówiła, że kurier jest rozdany za darmo, bo nikt nie pyta o `courier_shipping`. **To nieaktualne:** bramka siedzi w `Shop::courierAvailable()` i `parcelLockerAvailable()`, a wszystkie uprawnienia z cennika (`online_payments`, `invoices`, `ga_analytics`, `discount_codes`, `bulk_mail`, `order_editing`, `max_products`, `ai_weekly_limit`) mają dziś realne wywołania `entitlement()`. Klucz `custom_domain` **zniknął z configu** — kolumna `shops.domain` istnieje i `Shop::host()` jej używa, ale nie ma żadnego UI, więc własna domena nie jest do sprzedania (patrz [[handoff-2026-08-04]]).

## Usuwanie sklepów — DOSZŁO 2026-08-04
Konsola admina ma teraz kasowanie sklepu razem z kontem właściciela (natychmiast, potwierdzenie przez wpisanie nazwy) oraz zatrzymanie usunięcia zleconego przez sprzedawcę. Szczegóły: [[plan-shop-self-deletion]].
