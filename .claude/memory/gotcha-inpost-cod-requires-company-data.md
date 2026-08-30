---
name: gotcha-inpost-cod-requires-company-data
description: "Pobranie (COD) w ShipX: API przyjmuje `cod` przy naszych usługach, ale zakup pada na company_data_missing, gdy konto InPost nie ma kompletnych danych firmy. Zweryfikowane na sandboxie 17.08."
metadata: 
  node_type: memory
  type: project
  originSessionId: 7929e120-c8f7-42f2-8183-8ccb90e0b8c4
  modified: 2026-08-17T15:17:32.654Z
---

**Sonda na sandboxie 17.08.2026** (sklep #7 „Domowe Lemoniady", org 6700), cztery przesyłki: paczkomat i kurier C2C, każda w wersji z pobraniem i bez.

## Co działa

`cod` i `insurance` to **zwykłe atrybuty przesyłki**, nie osobna usługa — ShipX przyjął je (HTTP 201) przy OBU usługach, których używamy: `inpost_locker_standard` oraz `inpost_courier_c2c`. Żadnego `carrier_unavailable` ani `trucker_ID_is_not_set_for_organization`, czyli **konto prepaid bez umowy kurierskiej NIE jest przeszkodą dla pobrania**. Twardy warunek z dokumentacji: `insurance` ≥ `cod`.

## Co blokuje

Przesyłki z pobraniem stanęły na `offer_selected` i nie doszły do `confirmed`, a w `transactions[].details` siedzi:

```json
{"status":422,"error":"company_data_missing","message":"customer_lack_of_address_data"}
```

Próby kontrolne **bez** pobrania na tym samym koncie kupiły się od razu (`confirmed`, tracking, etykieta). Czyli: **pobranie wymaga pełniejszych danych organizacji w Managerze Paczek niż zwykła wysyłka** — dane adresowe firmy, a wg FAQ InPostu także numer rachunku bankowego (bez rachunku paczka pobraniowa wisi).

## Jak to zapamiętać

- Objaw jest **cichy**: HTTP 201, przesyłka istnieje, tylko nigdy nie zostaje opłacona. Bez zaglądania w `transactions` wygląda jak awaria Kramio.
- FAQ InPostu mówi o zawisie na `offer_prepared` — u nas było `offer_selected`. **Nie przywiązywać się do konkretnego statusu, patrzeć na ostatnią transakcję.**
- Nasz `ShipxClient::failureReason()` to złapie, ale zwróci bezużyteczne „InPost odrzucił nadanie (kod: company_data_missing)". Przy wdrażaniu pobrania dopisać własny case z instrukcją, co sprzedawca ma uzupełnić.

## Domknięcie (17.08, po uzupełnieniu rachunku)

Rafał dodał numer rachunku do profilu sandbox i **wszystkie cztery przesyłki przeszły na `confirmed`**, etykiety PDF pobrały się dla obu pobraniowych. Przyczyną był wyłącznie brak rachunku — mimo że komunikat mówił o danych adresowych.

Do kodu weszło: własny case w `ShipxClient::failureReason()` z instrukcją wymieniającą **rachunek jako pierwszy**, plus dane firmy i dane do faktury (sprzedawcy może brakować dowolnego z trzech).

**Druga rzecz z tej samej sondy:** po `confirmed` kwoty pobrania NIE DA SIĘ zmienić (`PUT` → 400 `shipment_status_incorrect`) ani anulować przesyłki (to samo). Okno na zmianę istnieje tylko między utworzeniem a automatycznym zakupem — czyli sekundy. Stąd zamek na edycję zamówienia od momentu nadania.

Powiązane: [[plan-cash-on-delivery]], [[plan-shipping]], [[plan-inpost-courier]].
