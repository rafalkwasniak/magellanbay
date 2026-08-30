---
name: plan-subscription-expiry
description: "MODUŁ KOMPLETNY 2026-07-30 (W1–W4): wygaśnięcie abonamentu jako STAN odczytu, karencja 7 dni, zamek produktów po najmniej popularnych, maile 14/7/1 + mail o wyłączeniu. Wszystkie 5 pytań rozstrzygnięte."
metadata: 
  node_type: memory
  type: project
  originSessionId: 97c7f155-ba79-478d-99e9-6f752187fda0
  modified: 2026-07-30T10:50:16.927Z
---

**Status: WDROŻONE W CAŁOŚCI 2026-07-30 (W1 + W2–W4).** Historycznie: propozycja z 2026-07-27, Rafał „mamy na to duuużo czasu"; siedliśmy do tego tego samego tygodnia, bo pierwsza sprzedaż pakietu już działa.

## Diagnoza (zweryfikowana w kodzie 2026-07-27)
`Shop::entitlement()` (Shop.php ~874) czyta snapshot `entitlements` i **nigdy nie pyta o datę ani o `comped`**. Efekt: `subscription_ends_at` i `comped` to czyste dane opisowe — sprzedawca po końcu abonamentu ma wszystko dalej. Konsola admina ([[plan-packages]] Faza 2) ustawia oba pola, ale nikt ich nie czyta.

## ZASADA NACZELNA: wygaśnięcie to STAN ODCZYTU, nie przepisanie danych
Kuszące jest nadpisać snapshot uprawnieniami Kramu przy wygaśnięciu — **to błąd**: skasowałoby ręczne nadania (moduł dany komuś gestem poza pakietem) i po opłacie nie byłoby jak ich odtworzyć. Sprzeczne z ustaleniem „uprawnienia LEPKIE" z [[plan-packages]].

Zamiast tego snapshot zostaje nietknięty NA ZAWSZE, a zmienia się sposób jego odczytu:
```
Shop::subscriptionActive()  → comped? || pakiet darmowy? || data w przyszłości?
Shop::entitlement($k)       → aktywna ? snapshot : uprawnienie pakietu DARMOWEGO (Kram)
Shop::rawEntitlement($k)    → zawsze snapshot (konsola admina: „co klient kupił")
```
Konsekwencja: **odnowienie = zmiana jednej daty**, wraca wszystko łącznie z ręcznymi dodatkami. Wygaśnięcie i odnowienie są odwracalne z definicji, zero migracji danych.

## Miękki zamek produktów — jedyna nowa kolumna
Kram = 24 produkty; sklep z 90 musi schować 66. „Po opłacie wracają jednym ruchem" jest NIEWYKONALNE bez znacznika: nie odróżnimy produktów schowanych przez system od tych, które sprzedawca ukrył sam (koniec sezonu) — przywracanie zgadywałoby.

Propozycja: `products.auto_hidden_at` (nullable timestamp). System ustawia przy zamku, czyści przy przywróceniu; ręczne ukrycie go nie dotyka. Kolejność ukrywania: **najstarsze ponad limit** (deterministycznie), sprzedawca może potem sam przełączyć, które 24 zostawia. Wpina się w istniejący `is_active` + [[shop-visibility-auto-publish]] (ProductObserver) — zero nowej architektury.

## Co gaśnie samo, a co wymaga wyjątku
Większość bramek zadziała bez zmian, bo pytają `entitlement()`: płatności online, faktury, kurier+paczkomat, GA, edycja zamówienia, kody rabatowe (bramka po obu stronach, [[plan-discount-codes]]). Dwa miejsca wymagają decyzji:
- **Zamówienia czekające na płatność online** — jeśli bramka zgaśnie z dnia na dzień, klient nie dokończy przelewu za wczorajsze zamówienie i pieniądze utkną, a winnym będzie sklep. REKOMENDACJA: pozwolić dokończyć płatność zamówieniom sprzed wygaśnięcia.
- **Kurier w zamówieniach w toku** — spokojnie: metoda dostawy to migawka na zamówieniu, realizacja idzie dalej. Gaśnie tylko wybór kuriera w kasie.

## Karencja i komunikacja
Roczny abonament płacony przelewem znaczy, że przegapienie terminu jest NORMALNE, nie złośliwe — zejście sekundę po północy wygeneruje wyłącznie telefony do Rafała.
Propozycja: przypomnienia **14 / 7 / 1 dni** przed końcem, mail w dniu wygaśnięcia, potem **7 dni karencji** z pełnymi funkcjami i wyraźnym banerem w panelu, zamek dopiero po niej. Wzorzec gotowy: outbox ([[email-outbox-cron-pattern]]) + `subscriptions:check` raz dziennie, `withoutOverlapping`, idempotentny. Scheduler już chodzi (`routes/console.php`, cron co minutę).

## Fazy
- **W1 — WDROŻONE 2026-07-29, commit `ec446f0`.** `Shop::subscriptionActive()` (comped || pakiet darmowy || data w przyszłości) + `entitlement()` czytający po wygaśnięciu uprawnienia Kramu + `rawEntitlement()` (snapshot, dla konsoli admina) + 10 testów. Pusta data przy płatnym pakiecie = BEZTERMINOWO (rozstrzygnięcie pytania 1). Bramki gasną bez zmian u siebie, bo pytają `entitlement()`. Konsola admina czyta `rawEntitlement`, inaczej pierwszy zapis po wygaśnięciu wykasowałby zakup. Przy okazji naprawione: `ShopManager::save()` gubił `ai_weekly_limit` ze snapshotu (każdy zapis kasował ręcznie nadany limit AI).
- **W2 — WDROŻONE 2026-07-30.** `products.auto_hidden_at` + `App\Services\ProductLimitLock` (`enforce()` / `restore()`). Kolejność ukrywania: **najmniej sprzedane, przy remisie najstarsze** (decyzja Rafała: „najlepiej chować te najmniej popularne — mimo wszystko"). Sprzedaż liczona BEZ anulowanych i bez `returned_quantity` — zwrot nie chroni produktu. Przywracanie idzie ODWROTNIE (najpierw bestsellery) i tylko tyle, ile wchodzi w limit; produkty ukryte RĘCZNIE (bez znacznika) są nietykalne w obie strony. Wpięte w `PackagePaymentService::apply()` (po wpłacie) i `ShopManager::save()` (restore + enforce, bo admin może i przedłużyć, i obciąć limit). 8 testów.
- **W3 — WDROŻONE 2026-07-30.** `App\Services\SubscriptionLifecycle` + komenda `subscriptions:check` (scheduler `dailyAt('06:10')`, `withoutOverlapping`). Tabela `subscription_notices` (shop_id + kind + **ends_at** w kluczu unikalnym → po odnowieniu przypomnienia ruszają same od nowa, bez czyszczenia). Progi z `config('shop.subscription.reminder_days')`. Warunek to „≤ X dni", nie „= X" — gdyby cron nie odpalił, próg nie przepadnie; nadrabianie wysyła tylko NAJBARDZIEJ NAGLĄCY próg. Baner stanu abonamentu w `layouts/panel` nad KAŻDYM ekranem panelu (poza „Mój pakiet"). 11 testów.
- **W4 — WDROŻONE 2026-07-30.** Karencja `config('shop.subscription.grace_days')` = 7: `subscriptionLocksAt()` = termin + karencja, `subscriptionActive()` mierzy do TEGO momentu, `inSubscriptionGrace()` + `graceDaysLeft()` napędzają amber-baner „czeka na opłatę". Rozdzielenie „termin zapłaty" (data na fakturze, niezmienna) od „chwili wyłączenia" jest sednem. **NIE zrobione z W4:** przycisk „przedłuż o rok" w konsoli admina i licznik wygasających — drobiazgi wygody, idą z resztą panelu admina.

## PIĘĆ PYTAŃ — WSZYSTKIE ROZSTRZYGNIĘTE
1. **Pusta data przy PŁATNYM pakiecie** → BEZTERMINOWO (W1).
2. **Karencja** → 7 dni, z pełnymi funkcjami i banerem; `grace_days = 0` daje zamek w terminie.
3. **Które produkty chować** → automat po popularności (decyzja Rafała), sprzedawca może potem sam przełączyć.
4. **Płatności w toku** → DOKOŃCZYĆ. `Shop::canFinishOnlinePayment()` (czyta `rawEntitlement`) w `Storefront\PaymentController::pay`. Bez dodatkowego warunku na datę: zamówienie czekające na płatność online mogło powstać TYLKO przy otwartej bramce, więc samo jego istnienie jest dowodem.
5. **Naklejka po wygaśnięciu** → **przestawić na Kram** (decyzja Rafała, wbrew mojej rekomendacji). Zrobione BEZ ruszania bazy: `effectivePackage()` / `effectivePackageName()` rozwiązują nazwę przy ODCZYCIE (Pulpit, Produkty, komunikat limitu, „Mój pakiet"), a plakietka obok mówi „Pawilon wygasł". Snapshot i kolumna `package` nietknięte, więc odnowienie to dalej zmiana jednej daty i ręczne nadania wracają. Konsola admina czyta `package`/`rawEntitlement` wprost — musi pokazywać, co klient kupił.

## Maile (decyzja Rafała: jedna treść, różny czas)
Przypomnienie jest JEDNYM tekstem dla wszystkich progów i mówi **datę**, nie „za ile dni" — progi da się zmienić w configu bez pisania nowych tekstów, a sprzedawca dostaje trzy spójne wiadomości. Wymienia karencję wprost, żeby spóźniony przelew nie budził paniki. Mail w chwili wyłączenia mówi, co się zmieniło, ILE produktów schowaliśmy, że nic nie usunięto — i listuje, co wróci po opłacie z **`PackageFeatures::forShop($shop, raw: true)`** (stan efektywny mówiłby o Kramie, czyli o niczym).

Powiązane: [[plan-packages]] (snapshot, lepkość uprawnień, miękki zamek), [[plan-per-shop-custom-pricing]], [[plan-admin-panel-and-landing]], [[pricing-packages]].
