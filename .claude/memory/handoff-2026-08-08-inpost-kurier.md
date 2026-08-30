---
name: handoff-2026-08-08-inpost-kurier
description: "Handoff 2026-08-08 (druga część dnia): rozmowa + sonda API, ZERO kodu. InPost umie kuriera pod adres i odbiór kuriera po paczki — udowodnione. Furgonetka wypada. Jutro zaczynamy pisać: pełna specyfikacja w plan-inpost-courier."
metadata: 
  node_type: memory
  type: project
  originSessionId: 5842dd7c-30c2-444b-a5c8-2c82c4c79846
  modified: 2026-08-08T19:02:39.299Z
---

**Sesja rozmowna i badawcza — ŻADNEGO KODU. Drzewo git czyste, ostatni commit `6e1dca9` (ten sam co na starcie dnia).** Cały dorobek siedzi w pamięci, nie w repo. Wcześniejsza część dnia → [[handoff-2026-08-08]] (charakter sklepu + audyt obietnic landingu).

## Co się wydarzyło
Rafał zapytał o Furgonetkę („mamy sandbox? jak to zrobić, żeby miało ręce i nogi?"). Przeanalizowałem brokerów, zarekomendowałem Furgonetkę — i wtedy Rafał zadał pytanie, które wywróciło plan: **„jeśli InPost może odebrać paczkę od nadawcy i dostarczyć pod adres, to mamy gotowy przykład na dostawę kurierem?"**

Potem drugi jego ruch, jeszcze lepszy: **„może łatwiej będzie, jak spróbujesz wysłać nową przesyłkę właśnie na adres"**. Zamiast dalej czytać dokumentację — sonda na sandboxie. W kwadrans odpowiedziała na wszystko, na co dokumentacja i mail do InPostu odpowiadałyby dniami.

## Wynik — trzy rzeczy udowodnione empirycznie
1. **Kurier pod adres DZIAŁA** — pełna ścieżka do wydrukowanej etykiety PDF, usługa `inpost_courier_c2c`, tryb uproszczony taki sam jak przy paczkomacie (nasz job i cron bez zmian).
2. **Odbiór kuriera po paczki DZIAŁA — jednym zleceniem dla WIELU przesyłek, także mieszanych** (paczkomatowa + kurierska razem). To odpowiedź na „sprzedawca z 30 paczkami dziennie": dopłata jest za PRZYJAZD, nie za paczkę.
3. **Bloker regulaminowy ZDJĘTY** — regulamin Managera Paczek dzieli usługi po TYPIE KONTA, nie po nazwie; konto Rafała to Firma krajowa.

**Konsekwencja: [[shipping-aggregator-idea]] zamknięta. Furgonetka wypada, sprzedawca nie podpisuje żadnej dodatkowej umowy.**

## DOGRYWKA WIECZOREM — plan uzgodniony w całości + jedna pułapka złapana
- **Rafał zaproponował: „domyślnie zawsze wrzucenie do paczkomatu, a paczki dla kuriera wybierzemy potem na ekranie zbiorczym".** Sprawdziłem — **NIE DZIAŁA**: `sending_method` jest wiążący, przesyłka wchodzi w stan `CustomerDelivering`, zlecenie odbioru zwraca 201 i po chwili `rejected`, a zmiana deklaracji po nadaniu daje 400. Szczegóły i dowody → [[plan-inpost-courier]].
- **Uratowana została jednak cała jego intuicja:** „kupujący określa JAK MA DOSTAĆ, a nie jak ma zostać wysłane" — potwierdzone, obie metody dostawy da się oddać kurierowi. Model „jedno ustawienie, opcje identyczne dla każdej wysyłki" zostaje; zmienia się tylko moment decyzji (przy nadaniu, nie później).
- **Złapana pułapka wdrożeniowa: `201` przy zleceniu odbioru NIE znaczy sukces** — odrzucenie przychodzi asynchronicznie w `errors`. Ten sam wzorzec, co przy nieudanym zakupie przesyłki. Bez odpytywania sprzedawca siedziałby w domu, a kurier by nie przyjechał.
- **Decyzje kroku 0 zamknięte**, kolejność 8 kroków uzgodniona, miejsce ustawień wskazane przez Rafała (wcięty blok pod kartą „Nadawanie przesyłek InPost" w Integracjach — reużywa wzorca „Auto-FV pod Paynow").

## GDZIE ZACZĄĆ JUTRO
**Czytać [[plan-inpost-courier]] — tam jest komplet:** payload, jednostki (mm/kg), sposoby nadania z kosztami, pułapka numeru mieszkania, zlecenia odbioru, plan wdrożenia w 5 punktach z nazwami metod, gotchy sandboxa. Ta notatka to tylko kontekst.

Pierwszy krok kodu: wariant kurierski w `ShipxClient::buildPayload()`. Największa praca: ekran nadawania z wagą i wymiarami zamiast trzech kafelków gabarytu.

## Czego NIE zrobiłem — i o czym pamiętać
- **Nie domknąłem cen.** `rate` i `price` wracają jako `null` na tym koncie (dokumentacja: klienci debetowi nie dostają cen). **Nie wiadomo, ile realnie kosztuje dopłata za przyjazd kuriera** — to pytanie na produkcję, nie na sondę.
- **Nie testowałem na produkcji** (saldo 0,00 zł, org 203242). Sandbox (org 6700) jest zasilony ~400 zł wirtualnych.
- Sonda zostawiła na sandboxie kilka przesyłek i jedno zlecenie odbioru `id=68050` — bez konsekwencji, pieniądze wirtualne.
- Rafał wkleił do `docs/dok.md` treść dokumentacji „Przesyłka" i potem plik usunął. **Wszystko, co z niego wynikało, jest w [[plan-inpost-courier]]** — usunięcie nic nie kosztuje.

## Wzorzec do zapamiętania (drugi raz w tym tygodniu)
**Rafał kolejny raz przeciął moją analizę jednym pytaniem „a może po prostu spróbuj".** 07.08 znalazł dwa błędy prawne w liczeniu 14 dni; dziś zaoszczędził cały tor Furgonetki. Ja szedłem w stronę „zapytajmy InPost i poczekajmy na odpowiedź", on w stronę „strzel do API i zobacz". **Przy integracjach z sandboxem: najpierw sonda, potem czytanie i maile.** Patrz [[plan-shipping]] — próba generalna curl-em przed kodem zadziałała tak samo dobrze 07.08.

Druga lekcja, moja: **nadinterpretowałem nazwę `c2c`** i zbudowałem na niej blokera („consumer-to-consumer, więc sklepowi nie wolno"). Rozstrzygnął zwykły regulamin wskazany przez Rafała. Nazwa identyfikatora technicznego nie jest kwalifikacją prawną.
