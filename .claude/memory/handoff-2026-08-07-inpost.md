---
name: handoff-2026-08-07-inpost
description: "InPost ShipX wdrożony CAŁY w jedno popołudnie (sandbox) — nadawanie, etykieta, data odbioru, maile; przy okazji dwa błędy prawne w liczeniu 14 dni znalezione przez Rafała; testy 1339 → 1400"
metadata: 
  node_type: memory
  type: project
  originSessionId: f3a08704-6120-421a-b155-84268c45b158
  modified: 2026-08-07T18:16:20.246Z
---

# Handoff 2026-08-07 (cz. 2) — pakiet InPost od zera do działania

Druga część tej samej sesji (cz. 1: [[handoff-2026-08-07-szablony-i-dokumenty]]). Testy **1339 → 1400**. Commity `015a81e` … `6d3e293`, wszystko wypchnięte, drzewo czyste.

## Jak to poszło — kolejność warta powtórzenia

**Najpierw ręczna próba curl-em na sandboksie, POTEM kod.** Przejechałem cały przepływ (points → utworzenie → status → etykieta) zanim powstała pierwsza klasa. Dzięki temu klient został napisany pod ZAOBSERWOWANE zachowanie API, a nie pod dokumentację — i wyłapaliśmy rzeczy, których w dokumentacji nie ma (patrz [[plan-shipping]], sekcja „PRÓBA GENERALNA"). Ta kolejność zdjęła praktycznie całe ryzyko techniczne wdrożenia.

Potem cztery kroki z przystankami na froncie (integracja → klient → panel → dopieszczenie), zgodnie z [[incremental-checkpoints-per-element]]. Rafał sprawdzał każdy krok okiem i wyłapał realne błędy.

## Co Rafał znalazł (ważniejsze niż kod)

1. **„Kliknąłem Nadaj i nic"** → stan „w trakcie" opierał się na `shipment_id`, który pojawia się dopiero po wykonaniu zadania z kolejki. Naprawione własnym statusem `queued` ustawianym PRZED `dispatch`.
2. **„Czy termin nie liczy się od Zrealizowane?"** → odkrył, że przy braku tego statusu liczyliśmy od DATY ZŁOŻENIA. Przy rękodziele robionym tygodniami termin mijał przed dostawą i **zamykał formularz zwrotu klientowi, któremu prawo dopiero zaczynało biec**.
3. **„Nie powinno się dać zgłosić zwrotu przed dostawą"** → nowe `Order::hasBeenHandedOver()`.
4. **Pomysł na `delivered_at`** — data odbioru z InPostu jako dokładny start 14 dni. Jego pomysł, nie mój.
5. **Odrzucił moją propozycję wyciszania maili** z dobrym uzasadnieniem (InPost wysyła własne 2 maile; realnie rozłożone w czasie).

**Wniosek na przyszłość: dopytywać Rafała o reguły biznesowe i prawne.** Zna swój produkt i klienta lepiej; dwa razy dziś trafił w rzeczy, których nie widziałem.

## Gotchy techniczne (szczegóły w [[plan-shipping]])

- Stan „w trakcie" NIE po `shipment_id`; ponowienie MUSI kasować ślad (guard `hasShipment` inaczej blokuje na zawsze).
- Telefon do ShipX bez prefiksu `+48`.
- Nieudany zakup przesyłki zwraca **200** — powód siedzi w `transactions[].details`.
- Przy zasilonym koncie zakup dzieje się SAM; jawne `buy` zwraca wtedy 400 (to nie błąd).
- Sandbox: 404 na istniejącej przesyłce przy szybkich zapytaniach → null znaczy „nie wiem", nie „nie istnieje".
- **Tailwind: `gap-x-2` i `gap-y-1` NIE ISTNIEJĄ w buildzie** (jest `gap-2`) — tekst skleił się bez odstępu, klasa cicho nic nie zrobiła. Kolejny raz ta sama pułapka: [[tailwind-classes-must-exist-in-build]].
- Carbon: `$deadline->diffInDays(now())` daje wynik UJEMNY — liczyć `now()->diffInDays($deadline)`.
- **Test-gotcha:** `actingAs` trzyma TEN SAM obiekt użytkownika między żądaniami testu → relacja załadowana przy wcześniejszym GET jest nieświeża przy POST. Ratunek: `actingAs($seller->fresh())`.
- `created_at` w `OrderStatusEvent` NIE jest wypełnialne — datę cofa się osobnym `forceFill(...)->save()`.

## Wzorzec wart powtórzenia: strażnik bezpieczeństwa, który sprawdzono

`ShipxTokenNeverLeaksTest` pilnuje, że token (umie nadawać paczki na koszt sprzedawcy) nie trafi do HTML panelu, storefrontu, kasy, konta klienta ani nagłówków etykiety, i że w bazie leży zaszyfrowany.

**Po napisaniu WSTRZYKNĄŁEM sztuczny wyciek do widoku i sprawdziłem, że test się zapala.** Strażnik, którego nie sprawdziłeś, że łapie, jest tylko dekoracją. Tak robić z każdym testem bezpieczeństwa.

## Stan na następną sesję

Pakiet InPost jest kompletny na sandboxie i przejechany przez Rafała od A do Z (zamówienie → nadanie → etykieta → symulowany odbiór → maile → zwrot). **Zostało:** finalny test na produkcji za ~20 zł (jego decyzja, kiedy), zamawianie kuriera po odbiór (jeden przycisk na gotowym kliencie). **Odpuszczone świadomie:** nadawanie zbiorcze (brak ruchu).

Poprzedni priorytet z [[priorities-launch-first]] — wysyłki — jest zamknięty. Następny w kolejce: **panel admina** ([[plan-admin-panel-and-landing]]).

Powiązane: [[plan-shipping]], [[legal-consumer-returns-withdrawal]], [[email-outbox-cron-pattern]], [[plan-order-statuses]].
