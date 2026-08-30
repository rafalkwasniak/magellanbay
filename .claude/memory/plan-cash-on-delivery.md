---
name: plan-cash-on-delivery
description: "WDROŻONE 17.08 (commit 0628285): przesyłki za pobraniem InPost. Pobranie = dwie metody DOSTAWY z własną ceną, nie metoda płatności. Zweryfikowane end-to-end na sandboxie."
metadata: 
  node_type: memory
  type: project
  originSessionId: 7929e120-c8f7-42f2-8183-8ccb90e0b8c4
  modified: 2026-08-17T15:30:03.330Z
---

**Status: KOMPLETNE 17.08.2026, commit `0628285`.** Zweryfikowane end-to-end: Rafał złożył zamówienie na `lemoniady.kramio.pl`, ja nadałem przesyłkę — InPost dostał kwotę 39,89 zł co do grosza.

## Decyzja architektoniczna (Rafał)

**Pobranie to METODA DOSTAWY, nie płatności.** Dwie nowe: `courier_cod` i `parcel_locker_cod`, każda z własną ceną i własnym progiem darmowej dostawy, każda z osobnym przełącznikiem. Powód: sprzedawca ustala im cenę i włącza je tak samo jak resztę dostaw, więc cennik i włącznik dostajemy z istniejącego mechanizmu zamiast budować drugi.

**Cztery fiszki są NIEZALEŻNE** — da się mieć sam paczkomat i sam paczkomat pobraniowy. Rafał wprost odrzucił łączenie ich w jeden przełącznik.

`PaymentMethod::CashOnDelivery` istnieje, ale **klient go nie wybiera** — ustawia się z dostawy, a kasa zamiast listy płatności pokazuje zdanie. Enum ma `isChosenByCustomer()`.

## Rzeczy, które łatwo zepsuć przy zmianach

- **Metody pobraniowe wymagają `shipxEnabled()`** — inaczej niż zwykły kurier, który bywa dostawą własną za 0 zł. Bez konta InPost nie ma kto zainkasować pieniędzy.
- **`Shop::acceptsOrders()` liczy pobranie jako drogę do zapłaty** obok `availablePaymentMethods()`. Bez tego sklep z samym pobraniem wpada w tryb katalogu — czyli dokładnie ten sprzedawca, dla którego to powstało (żadnego Paynow, żadnych przelewów).
- **Statusy BEZ zmian** — brak kroku „Opłacone". Fakt zapłaty niesie `delivered_at` (bez zapłaty klient paczki nie odbierze), a osobny status byłby mailem „opłacone" do kogoś, kto trzyma paczkę w ręku.
- **Limit kwoty sprawdzany W KASIE**, nie przy nadawaniu (`config('shop.cash_on_delivery.max_amount')`: 5 000 paczkomat / 15 000 kurier, potwierdzone przez Rafała). Inaczej klient dostaje potwierdzenie zamówienia, którego nie da się wysłać.
- **Edycja zamówienia zablokowana OD NADANIA**, nie od metody płatności — patrz [[gotcha-inpost-cod-requires-company-data]]. Gałąź plakietki w widoku musi stać PRZED upsellem pakietu, inaczej Pawilon czyta, że potrzebuje wyższego pakietu.
- **Etykiety pobraniowe to doklejka do bazowych** („Kurier" → „Kurier za pobraniem"). Rafał zgłosił to jako niespójność — pary muszą się rymować na każdym ekranie.
- **Nigdzie nie obiecywać gotówki przy paczkomacie.** Paczkomat przyjmuje BLIK/kartę/aplikację; gotówka tylko w oddziale. Dotyczy kasy, maila i regulaminu.

## Teksty (commit `5242e72`)

Landing, cennik, Integracje i formularz zwrotu **mówią już o pobraniu**. Zmiana w `PackageFeatures` rozeszła się sama na landing, stronę pakietu i maile — potwierdzenie, że to naprawdę jedno źródło ([[landing-promises-drift-audit]]).

Akapit na landingu stoi **zaraz pod tym o Paynow**, bo pobranie jest odpowiedzią na „musisz zawrzeć umowę z operatorem". Warunki (konto InPost, płatny pakiet) podane wprost.

**Opłacanie PAKIETÓW zostaje bez wzmianki** — pobrania za pakiety nie oferujemy, więc byłaby to obietnica bez pokrycia. Nie „przeoczenie", tylko decyzja; nie dopisywać przy kolejnym audycie.

## Co zostało poza kodem

Rozliczenia pobrań sprzedawca ogląda w Managerze Paczek: zasób „Raport COD" w ShipX obejmuje tylko `inpost_courier_standard` i ekspresy, a my nadajemy przez `inpost_locker_standard` i `inpost_courier_c2c`.

Powiązane: [[plan-shipping]], [[plan-inpost-courier]], [[plan-catalog-mode-no-cart]].
