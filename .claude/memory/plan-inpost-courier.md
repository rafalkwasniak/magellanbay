---
name: plan-inpost-courier
description: INPOST UMIE KURIERA POD ADRES — udowodnione empirycznie 2026-08-08 (etykieta wydrukowana na sandboxie). Bloker regulaminowy ZDJĘTY. Furgonetka WYPADA z planu. Gotowy plan wdrożenia na istniejącym szkielecie ShipX — czytać PRZED pisaniem kodu wysyłek.
metadata: 
  node_type: memory
  type: project
  originSessionId: 5842dd7c-30c2-444b-a5c8-2c82c4c79846
  modified: 2026-08-09T13:53:40.441Z
---

**✅ MODUŁ ZAMKNIĘTY 2026-08-09.** Rafał przejechał całość i potwierdził: **„InPost paczkomaty i kurier mamy domknięte, działa"**. Commit `ff9b086`, 1456 testów. Nie planować tu nic więcej bez nowego sygnału — zbiorcze etykiety mają osobną, świadomą decyzję NIE (sekcja niżej), a test produkcyjny czeka na pierwszego realnego sprzedawcę (sekcja OTWARTE).

Poniżej zostaje pełne rozpoznanie API i uzasadnienia decyzji — do czytania, gdy temat wróci, nie do wykonania.

## Sedno w jednym zdaniu
**InPost nadaje przesyłki kurierskie pod adres na koncie, które sprzedawca już ma — bez rozszerzania umowy, bez brokera, bez drugiej integracji.** Dlatego **Furgonetka wypada z planu** (decyzja Rafała 08.08: „jeśli to wyjdzie, zapominamy o Furgonetce i kolejnych umowach sprzedawcy"). Patrz [[shipping-aggregator-idea]] — pomysł zostaje w archiwum, nie w planach.

## KONTA — nie pomylić (Rafał sprostował 08.08)
- **SANDBOX = tu rozwijamy i testujemy.** Organizacja **6700**, sklep `lemoniady` (id=7) w bazie, `sandbox-api-shipx-pl.easypack24.net`. **Saldo zasilone (~400 zł wirtualnych) — nadawanie działa, testy nic nie kosztują.**
- **PRODUKCJA = konto `sklep@kramio.pl`, organizacja 203242, saldo 0,00 zł.** Zrzut panelu stamtąd służył WYŁĄCZNIE do potwierdzenia, że usługa „InPost Kurier" jest na koncie.
- **⚠️ SPROSTOWANIE RAFAŁA 08.08 (wieczorem): sklep `lemoniady` to DEMO i NIGDY nie pójdzie na produkcję** — „tutaj nie sprzedaję". Po dopracowaniu InPosta Rafał po prostu **wyłączy tam integrację**. **Nie planować przełączania tego sklepu na konto produkcyjne ani zasilania salda** — to była moja błędna domyślna, przepisywana z planu paczkomatowego.
- Rozpoznanie środowiska po `iss` w JWT: `login.inpost.pl` = produkcja, `sandbox-login.inpost.pl` = sandbox.

## CO UDOWODNIONE EMPIRYCZNIE (sonda API 08.08 — te fakty > dokumentacja)
Pełna ścieżka przejechana: utworzenie → oferta → zakup → `confirmed` → numer → **etykieta PDF 22 772 B, HTTP 200, `application/pdf`**. Numery z sondy: `642582087600718021256174` (tryb pełny) i `642582087600718028761237` (tryb uproszczony).

1. **Usługa to `inpost_courier_c2c`.** `inpost_courier_standard` **NIE pojawia się w ofertach** tego konta — drugie, niezależne potwierdzenie ustalenia z 07.08 (nie ma go w umowie). C2C wystarcza w zupełności.
2. **POLE, KTÓREGO BRAKOWAŁO: `custom_attributes.sending_method`.** Bez niego **KAŻDA** oferta kurierska wraca `unavailable` z `unavailability_reasons: [{key: "sending_method_required"}]`. Przy paczkomacie tego nie widzieliśmy, bo tam zawsze ustawiamy `parcel_locker`. **Ustawienie „preferowany sposób nadania" z panelu InPostu NIE działa przez API — musimy je wysyłać jawnie za każdym razem.**
3. **TRYB UPROSZCZONY DZIAŁA TAK SAMO JAK PRZY PACZKOMACIE.** Podanie `service: inpost_courier_c2c` przy tworzeniu → InPost sam wybiera ofertę i **KUPUJE** → `confirmed` + `tracking_number`, bez naszego `POST /buy`. **Konsekwencja: `CreateInpostShipment`, `RefreshInpostShipments` i cała mechanika `ShipxClient` działają BEZ ZMIAN.** (Tryb pełny — bez `service` → `offers[]` → jawne `POST /v1/shipments/{id}/buy` z `offer_id` — też sprawdzony i działa, ale nie jest nam potrzebny.)
4. **`rate` = NULL.** Dokumentacja tłumaczy: klienci debetowi nie dostają cen w odpowiedzi. **Nie budować UI zakładającego, że cena zawsze przyjdzie.**
5. **KOREKTA mojego pomysłu „odpytamy konto o listę usług":** `GET /v1/organizations/{id}/services` daje **404**. Działa `GET /v1/services`, ale to **katalog SYSTEMOWY, nie możliwości konta** — `inpost_courier_standard` tam figuruje, choć konto go nie ma. **Jedynym wiarygodnym testem dostępności są OFERTY dla konkretnej przesyłki** (`offers[].status` + czytelne `unavailability_reasons`). Gdyby kiedyś przyszło pokazywać sprzedawcy wybór usług — robić to na ofertach, nie na katalogu.

## PAYLOAD KURIERA (różnice wobec paczkomatu)
```
receiver.address: street, building_number, city, post_code, country_code   ← zamiast custom_attributes.target_point
parcels[].dimensions: {length, width, height, unit: "mm"}                  ← zamiast template: small/medium/large
parcels[].weight:     {amount, unit: "kg"}
custom_attributes.sending_method: "parcel_locker" | "pok" | "dispatch_order"
service: "inpost_courier_c2c"
```
**JEDNOSTKI: wymiary WYŁĄCZNIE w `mm`, waga WYŁĄCZNIE w `kg`** — dokumentacja mówi wprost, że innych nie ma. Pola adresowe mamy już w `orders` z kasy (`ship_street`, `ship_building_number`, `ship_apartment_number`, `ship_postal_code`, `ship_city`).

**PUŁAPKA ADRESU: obiekt `Address` w ShipX NIE MA pola na numer mieszkania** — jest tylko `street` + `building_number` (plus przestarzałe `line1`/`line2`, których dokumentacja odradza). Nasze `ship_apartment_number` trzeba **skleić z numerem budynku** („157/4"), inaczej kurier pojedzie pod samą klatkę. Do sprawdzenia przy wdrożeniu, czy InPost przyjmuje ukośnik — jeśli nie, zostaje `line2`.

**Telefon: bez prefiksu `+48`** (9 cyfr) — gotcha znana z paczkomatu, `ShipxClient::localPhone()` już to robi, dotyczy tak samo kuriera.

## SPOSÓB NADANIA — panel InPostu i API mówią to samo, 1:1
Rafał zauważył to w Ustawieniach swojego panelu; trzy opcje z rozwijanego pola „Preferowany sposób nadania" = trzy wartości `sending_method`, które przetestowałem:

| Panel InPost | `sending_method` | koszt | uwaga |
|---|---|---|---|
| Nadam przesyłkę przez Paczkomat® | `parcel_locker` | darmowe | działa BEZ `dropoff_point` |
| Nadam przesyłkę w PaczkoPunkcie | `pok` | darmowe | **API zwraca 400 bez `dropoff_point`** |
| Utworzę zlecenie odbioru — odbierze kurier InPost | `dispatch_order` | **DODATKOWO PŁATNE** | tak pisze panel InPostu |

Działa też `any_point`. `courier_pok` — jak `pok`, wymaga `dropoff_point`.

**DECYZJA UI (wymuszona uwagą Rafała): domyślny sposób nadania MUSI być darmowy (`parcel_locker`), a odbiór kuriera świadomym wyborem z widoczną informacją o dopłacie.** Sonda pierwszy raz zadziałała na `dispatch_order` i zaszycie go jako domyślnego byłoby naturalnym odruchem — sprzedawca płaciłby wtedy za coś, czego nie zamawiał, i długo by tego nie zauważył.

**Rozbieżność dokumentacja-vs-rzeczywistość:** dokumentacja wymaga `dropoff_point` także dla `parcel_locker`; w praktyce sonda bez niego zwróciła 201 i dostępną ofertę (tracking pokazuje `dropoff_machine_id: "ANY-PM"`). Realnie wymagany tylko dla `pok`/`courier_pok`.

## ZLECENIE ODBIORU (kurier przyjeżdża po paczki) — UDOWODNIONE 2026-08-08, pytanie Rafała
**Rafał: „a jak ma zrobić sprzedawca, który sprzedaje 30 produktów dziennie i chce, żeby kurier po to przyjechał?"** Sprawdzone sondą, wszystko potwierdzone empirycznie.

**TO SĄ DWIE RÓŻNE RZECZY — nie mylić:**
1. `custom_attributes.sending_method: "dispatch_order"` na PRZESYŁCE = tylko **deklaracja** „tę paczkę oddam kurierowi". Sama nikogo nie wzywa.
2. **Zlecenie odbioru** = osobny obiekt, `POST /v1/organizations/{org}/dispatch_orders`, i to ono sprowadza kuriera.

**KLUCZOWE: zlecenie przyjmuje TABLICĘ przesyłek.** 30 zamówień = 30 przesyłek + **JEDNO zlecenie odbioru** → kurier przyjeżdża RAZ i zabiera wszystko. **Dlatego dopłata jest za PRZYJAZD, nie za paczkę** — przy realnym ruchu to grosze na paczkę.

**DZIAŁA DLA OBU METOD DOSTAWY, NAWET W JEDNYM ZLECENIU.** Sonda: przesyłka paczkomatowa (`inpost_locker_standard` + `target_point`) i kurierska pod adres (`inpost_courier_c2c` + `receiver.address`), obie z `sending_method: dispatch_order`, obie `confirmed` — objęte **jednym** zleceniem `id=68050`, status `new`, w odpowiedzi obie z numerami. Czyli sprzedawca oddaje kurierowi CAŁY dzienny urobek niezależnie od tego, jak klient wybrał dostawę.

**PUNKT ODBIORU NIE JEST POTRZEBNY — to „albo–albo".** Próba z `dispatch_point_id` **i** `address` naraz → 400 `dispatch_point_and_address_cannot_be_mixed`. **Sam `address` wystarczy i zwrócił 201.** Czyli podajemy adres pracowni sprzedawcy (mamy go w sklepie) i **zero konfiguracji po stronie panelu InPostu**. `GET /v1/organizations/{org}/dispatch_points` działa (na sandboxie pusto), ale **POST nie istnieje** (`routing_not_found`) — punkty definiuje się w panelu InPostu (zakładka „MOJE PUNKTY ODBIORU”), nie przez API. Nam niepotrzebne.

Ciało, które zadziałało: `{shipments:[id,...], address:{street,building_number,city,post_code,country_code}, office_hours:"09:00-17:00", comment:"..."}`. Odpowiedź: `id`, `status: "new"`, `statuses:[]`, `shipments:[{id,tracking_number}]`, `comments:[]`, `errors`, **`price: null`** (znowu brak cen na tym koncie).

### ⛔ `sending_method` JEST WIĄŻĄCY — deklaracja przy TWORZENIU, nie da się zmienić (sonda 08.08 wieczorem)
**Pomysł Rafała: „domyślnie zawsze wrzucenie do paczkomatu, a jak dorobimy »Zamów kuriera«, to tam wybierzemy paczki jeszcze nienadane — i te do paczkomatu, i te pod adres". SPRAWDZONE: NIE DZIAŁA.**

- Przesyłka utworzona z `sending_method: parcel_locker` wchodzi w stan logistyczny **`CustomerDelivering`** („klient dostarcza sam").
- Zlecenie odbioru na taką przesyłkę zwraca **201**, a po chwili **`status: "rejected"`** z `errors.details`: **„Parcel (…) status should be Prepared, but is CustomerDelivering."**
- **Dotyczy OBU metod dostawy** — sprawdzone osobno dla paczkomatowej i dla kurierskiej pod adres. To nie jest ograniczenie paczkomatu, tylko samej deklaracji nadania.
- **Zmiana po fakcie niemożliwa:** `PUT`/`PATCH /v1/shipments/{id}` → 400 `invalid_action` / `shipment_status_incorrect` (`shipment_status: confirmed`). Po opłaceniu przesyłka jest zamrożona.
- Kontrola pozytywna: zlecenie `68050` na przesyłki utworzone z `sending_method: dispatch_order` przeszło w status **`sent`**.

**PUŁAPKA WDROŻENIOWA: `201` przy tworzeniu zlecenia NIE znaczy sukces.** Odrzucenie przychodzi ASYNCHRONICZNIE — trzeba odpytać `GET /v1/dispatch_orders/{id}` i czytać `status` (`new` → `sent` albo `rejected`) oraz `errors`. **To dokładnie ten sam wzorzec, co przy nieudanym zakupie przesyłki (`transactions[].details`) — InPost systematycznie zgłasza porażki poza kodem HTTP.** Bez tego zlecenie „wisiałoby wysłane", a kurier by nie przyjechał.

**CO Z TEGO WYNIKA DLA PROJEKTU:** sprzedawca musi zadeklarować sposób oddania paczki **w momencie nadawania**, nie później. Model Rafała „jedno ustawienie, opcje identyczne dla każdej wysyłki" **zostaje w mocy** — po prostu ustawienie działa w chwili nadania, a ekran zbiorczy zbiera paczki JUŻ zadeklarowane jako `dispatch_order`.

**POTWIERDZENIE W PANELU INPOSTU (zrzut listy przesyłek, sandbox) — 1:1 z API.** Kolumny „Sposób nadania" i „Status" opowiadają całą historię:
| Deklaracja przy tworzeniu | Kolumna „Sposób nadania" | Status w panelu |
|---|---|---|
| `parcel_locker` | „Dowolny Paczkomat®" | **„Do nadania przez Paczkomat®"** — kurier nigdy po nią nie przyjedzie |
| `dispatch_order`, BEZ zlecenia | „Odbierze kurier InPost" | **„Utwórz zlecenie odbioru"** (panel sam mówi sprzedawcy, czego brakuje) |
| `dispatch_order` + zlecenie `sent` | „Odbierze kurier InPost" | **„Czeka na odbiór przez kuriera"** |

**Odrzucenie dotyczy ZLECENIA ODBIORU, nie przesyłki** — na liście przesyłek nie widać po nim śladu (Rafał słusznie zauważył, że „nic nie jest odrzucone"). Zlecenia to osobna lista. **Konsekwencja dla nas: stan zlecenia trzeba odpytywać osobno — przesyłka wygląda zdrowo także wtedy, gdy kurier po nią nie przyjedzie.**

**POTWIERDZONA CZĘŚĆ INTUICJI RAFAŁA:** „kupujący określa JAK MA DOSTAĆ, a nie jak ma zostać wysłane" — **tak, to prawda i jest niezależne.** Paczkę jadącą pod adres można wrzucić do paczkomatu, a paczkę do paczkomatu może zabrać kurier. Warunek: zadeklarowane z góry.

**PROJEKT NASZEGO PANELU (z tego wynikający):**
- Na zamówieniu: wybór sposobu nadania, **domyślnie darmowy**.
- **Osobny ekran „Zamów kuriera po odbiór": zaznacz gotowe przesyłki → jedno zlecenie → jedna dopłata.** To naturalnie zrasta się z odpuszczonym wcześniej **nadawaniem zbiorczym** — przy 30 paczkach dziennie jedno i drugie to ta sama czynność. Wtedy przestaje być „killer feature na potem", a staje się warunkiem obsłużenia sprzedawcy z ruchem.
- Adres odbioru bierzemy ze sklepu; nie zmuszamy sprzedawcy do zakładania punktu w panelu InPostu.

**GOTCHA SANDBOXA (potwierdzona ponownie): losowe `404 page not found` — zwykły tekst, nie JSON `routing_not_found`.** Ta sama ścieżka za drugim razem zwraca 200. **Rozróżnienie jest diagnostyczne: JSON `routing_not_found` = trasy NIE MA; goły tekst `404 page not found` = sandbox się zaciął, ponowić.** Bez tego rozróżnienia straciłem chwilę, uznając istniejący endpoint za nieistniejący.

## BLOKER REGULAMINOWY — ZDJĘTY REGULAMINEM, NIE MAILEM
Rafał wskazał `inpost.pl/regulaminy#aplikacje` → **„Regulamin aplikacji Manager Paczek" (od 23.07.2026)**. Rozstrzygające punkty **4.10–4.13**: konto występuje w trzech wariantach (Osoba prywatna / Firma krajowa / Firma zagraniczna); **4.12 wprost przewiduje konta firmowe „w celach bezpośrednio związanych z prowadzoną przez nich działalnością gospodarczą lub zawodową (nie jako konsumenci)"**; **4.13: „Zakres usług dostępnych dla każdego typu konta jest określony w aplikacji"**.

**Regulamin NIE dzieli usług po nazwie, tylko po TYPIE KONTA.** Nazwa `inpost_courier_c2c` to wewnętrzny identyfikator ShipX dla usługi **„InPost Kurier"** z Managera Paczek, a nie kwalifikacja prawna „tylko dla konsumentów". **Moja obawa („C2C = consumer-to-consumer, więc sklepowi nie wolno") była nadinterpretacją samej nazwy — odnotowane, żeby jej nie powtórzyć.**

Potwierdzenie faktyczne: w panelu produkcyjnym Rafała **„InPost Kurier" jest osobną pozycją w Ustawieniach** (własny domyślny rozmiar), czyli aplikacja tę usługę oferuje → zgodnie z 4.13 konto ma do niej prawo.

**Warunek dla KAŻDEGO sprzedawcy (nie tylko Rafała): konto typu FIRMA + usługa widoczna w jego panelu.** To trafia do instrukcji krok po kroku w naszym ekranie Integracji.

## PLAN WDROŻENIA — mało kodu, jeden realny ekran
Szkielet z 07.08 jest **metodo-agnostyczny** i nie wymaga zmian: kolumny `shipment_*`/`shipped_at`, `CreateInpostShipment` (`tries=1`, guard `hasShipment`), `shipments:refresh`, status `SHIPMENT_QUEUED`, trasa `seller.orders.label`, `delivered_at` → 14 dni, oba maile do klienta, `ShipxTokenNeverLeaksTest`.

### DECYZJE Z KROKU 0 — ZAMKNIĘTE 08.08 (Rafał)
- **Domyślna paczka w Ustawieniach**, widoczna gdy kurier włączony i InPost skonfigurowany. TAK.
- **Sposób nadania ustawiany RAZ, nie przy każdej paczce. Domyślnie Paczkomat.** PaczkoPunkt pomijamy w v1.
- **„Zamów kuriera po odbiór" na KOŃCU** (krok 7), razem z nadawaniem zbiorczym.

### POSTĘP — kroki 1–4 ZROBIONE 2026-08-08 wieczorem (potwierdzone okiem Rafała: „wszystko działa pięknie")
Migracje wykonane na produkcji. Powstały: `App\Enums\SendingMethod`, `App\Services\Shipping\ParcelSpec` (jedyne miejsce przeliczania cm→mm), kurierski wariant `ShipxClient::buildPayload()`, `Order::hasShipmentDestination()` i `shipmentParcelLabel()`, kurierskie pola w `OrderShipment` + widok. **742 testy zielone w grupach Shipping/Shop/Order/Seller/Mail. Rebuild frontu NIEPOTRZEBNY** — wszystkie użyte klasy sprawdzone w `public/build/assets` (`py-2.5` nie istnieje, podmienione na `py-3`; `grid-cols-4` i `col-span-2` też NIE ISTNIEJĄ — użyty `grid-cols-2`).
**Zweryfikowane na produkcyjnym zamówieniu 44** (sklep `lemoniady`, kurier pod adres).
Dwie wpadki po drodze: [[livewire-override-signature-fatal]] oraz obcinanie zer robiące z „30" — „3" (złapane testem).
**ZACOMMITOWANE 2026-08-08, commit `ff9b086`, pełna suita 1456 testów zielona.** Migracje wykonane na produkcji. Rebuild frontu NIE był potrzebny ani razu.

**Kroki 5–7 ZROBIONE tego samego wieczoru** (Ustawienia z wciętym blokiem, maile rozdzielone na paczkomat/kurier, ekran „Odbiór kuriera"). **757 testów zielonych w grupach Shipping/Seller/Order/Mail/Shop.** ZOSTAŁ krok 8 (produkcja) i odłożone nadawanie zbiorcze.

**Zmiana decyzji wymuszona przez Rafała (i słusznie):** sposób nadania jest wybierany TAKŻE przy każdym nadaniu (domyślnie z Ustawień), nie tylko globalnie. Powód: deklaracja jest nieodwracalna, więc bez tego każdy wyjątek oznaczał wycieczkę do Ustawień i z powrotem. Moja pierwotna rekomendacja („jedno ustawienie, koniec") optymalizowała ilość pracy, nie pracę sprzedawcy.

**Nowe gotchy z wdrożenia:**
- **`Http::fake()` wołane DRUGI RAZ nie nadpisuje pierwszej zaślepki** — dokłada się za nią, a wygrywa pierwsza. Test „utwórz, potem odpytaj status" musi rozróżniać odpowiedzi ADRESEM (`*/organizations/*/dispatch_orders` vs `*/v1/dispatch_orders/*`), nie kolejnością wywołań.
- Obcinanie zer przez `rtrim($x, "0")` robi z „30" — „3". Ciąć tylko gdy jest separator dziesiętny.
- Klas `grid-cols-4`, `col-span-2`, `py-2.5`, `pr-8`, `basis-0`, `sm:grid-cols-2` **NIE MA w buildzie** — cztery pola w rzędzie zrobione na `flex` + `flex-1 min-w-0`.
- [[livewire-override-signature-fatal]]

### KOLEJNOŚĆ KROKÓW (uzgodniona 08.08)
1. **Model danych** — migracje (`shops`: domyślna paczka + domyślny sposób nadania; `orders`: wymiary i waga faktycznie nadanej paczki). Nowy enum `SendingMethod` (`parcel_locker` / `dispatch_order`) z polskimi etykietami i oznaczeniem, który jest płatny. Casty. **`shipment_size` (gabaryt A/B/C) NIETKNIĘTY — opisuje skrytkę paczkomatu, przy kurierze nie znaczy nic.** Przystanek: zielone testy, nic nie widać.
2. **`ShipxClient` uczy się kuriera** — rozbić dzisiejsze `buildPayload()` (twardo paczkomatowe) na wariant paczkomatowy i kurierski. Adres, `dimensions`+`weight`, `sending_method` ZAWSZE jawnie, `service: inpost_courier_c2c`. Testy na `Http::fake()` o kształt żądania (mm/kg, adres, brak `target_point`).
3. **Bramka i widoczność** — `Order::canBeShipped()` ([Order.php:525](app/Models/Order.php#L525), dziś odrzuca wszystko poza paczkomatem) · `OrderShipment::render()` przez `isShipped()` zamiast `requiresParcelLocker()` · `requestShipment()` i `CreateInpostShipment` przyjmują opis paczki, nie sam `ParcelSize`.
4. **Ekran nadawania — NAJWIĘKSZA ROBOTA.** Paczkomat zostaje na trzech kafelkach; kurier dostaje wagę i wymiary podpowiedziane z Ustawień. Walidacja: liczby dodatnie, **limit 25 kg**, ostrzeżenie powyżej 120 cm (przesyłka niestandardowa, `is_non_standard`). **Przystanek: Rafał klika na sandboxie.**
5. **USTAWIENIA — miejsce wskazane przez Rafała 08.08: wcięty blok POD kartą „Nadawanie przesyłek InPost"** w sekcji Integracje na `/sprzedawca/ustawienia` (nie osobna karta w sekcji Dostawa — „żeby wszystko było w jednym miejscu"). Mieszczą się tam DWIE rzeczy: **domyślny sposób nadania** (Paczkomat / Odbierze kurier — płatne) i **domyślne wymiary + waga paczki kurierskiej**.
   - **Wzorzec ISTNIEJE w kodzie — reużyć, nie wymyślać:** „Wystaw fakturę VAT automatycznie po opłaceniu" wcięta pod Paynow w `seller/settings/edit.blade.php` (`<div class="mt-4 border-t border-stone-100 pt-4">` + `ml-4` na wcięcie). Tam też jest komentarz tłumaczący, *dlaczego* wcięcie: ustawienie zależy od rodzica.
   - **Podział pojęciowy, który to porządkuje:** sekcja **Dostawa** = co widzi KLIENT (metody i koszty), sekcja **Integracje** = jak pracuje SPRZEDAWCA (nadawanie). Sposób nadania i rozmiar paczki to drugie, nie pierwsze.
   - **Gotcha z istniejącego wzorca:** pola nieaktywne muszą mieć `<input type="hidden">` z zapisaną wartością, inaczej zapis ustawień je wyzeruje. Aktywne dopiero gdy `shipxConfigured` i `shipxEnabled`; wymiary paczki mają sens tylko przy włączonej dostawie kurierem — wtedy miękka podpowiedź zamiast blokady.
   - Przy `pok` byłoby jeszcze pole `dropoff_point` — **w pierwszej wersji PaczkoPunkt pomijamy** (tylko Paczkomat i Odbiór kuriera).
6. **Maile i widoki klienta.** Mail „Paczka w drodze" (`OrderMailer::shipmentDispatched()`, ~linia 118) jest dziś CZYSTO PACZKOMATOWY: mówi „Odbiór w paczkomacie" i „InPost powiadomi Cię SMS-em, gdy paczka dotrze do paczkomatu — wtedy dostaniesz kod do odbioru". **Przy kurierze oba zdania są NIEPRAWDĄ.** Potrzebny wariant: adres dostawy zamiast paczkomatu, inne zdanie o powiadomieniach. Plus drobiazg w panelu: wymiary zamiast „Gabaryt A" ([order-shipment.blade.php:33](resources/views/livewire/seller/order-shipment.blade.php#L33)).
7. **„Zamów kuriera po odbiór" + nadawanie zbiorcze** — ekran: zaznacz przesyłki zadeklarowane jako `dispatch_order` i jeszcze nieodebrane → JEDNO zlecenie → jeden przyjazd, jedna dopłata; przy okazji jeden PDF z etykietami (to ta sama czynność sprzedawcy). **MUSI odpytywać stan zlecenia — `201` to nie sukces (patrz pułapka wyżej).** Wzór z panelu InPostu: status przesyłki „Utwórz zlecenie odbioru" = dokładnie ta lista.
8. **Produkcja** — przełączenie sklepu na środowisko produkcyjne, zasilenie salda, jeden realny test.

Kolejność jak zawsze: małymi krokami z przystankami na froncie ([[incremental-checkpoints-per-element]]). W trakcie pracy testy FILTROWANE, pełna suita raz przed commitem ([[open-hosting-process-limit]]). Commit bez stopki generatora ([[no-coauthor-footer-in-commits]]).

## OTWARTE
- **KROK 8 NIE JEST TYM, CZYM MYŚLAŁEM.** Pierwotnie: „przełącz sklep na produkcję, zasil saldo, jeden realny test za ~20 zł". Po sprostowaniu Rafała **nie ma na czym tego zrobić** — `lemoniady` to demo. Realny test musiałby pójść przez sklep, który faktycznie sprzedaje (cudzy albo osobny Rafała), więc **do czasu pierwszego prawdziwego sprzedawcy ścieżka kurierska pozostaje przetestowana WYŁĄCZNIE na sandboxie**. To samo dotyczy zresztą paczkomatu (test produkcyjny wisi otwarty od 07.08). Nazwać to raz przy pierwszym sprzedawcy z InPostem, nie mielić.
## ⛔ NADAWANIE ZBIORCZE — NIE ROBIMY (decyzja Rafała 2026-08-09, po przetestowaniu całości)
**„Chyba na tym etapie nie potrzeba nam zbiorczych etykiet, bo raczej nikt nie będzie miał 30 zamówień dziennie. To jednak mały sklep."** Rafał uznał obecny przepływ za lepszy: wejść w zamówienie → ustawić status → pobrać etykietę → nakleić → następne. **Rytm per zamówienie ma ręce i nogi, a zbiorczy druk oszczędza kliknięcia dopiero przy wolumenie, którego nie ma.**

**Dlaczego ZBIORCZE ZAMÓWIENIE KURIERA zostaje, a zbiorcze etykiety nie** (pozorna niespójność, warta zapamiętania): zamawianie kuriera daje realną oszczędność **od pierwszej dodatkowej paczki** — jedna dopłata zamiast trzech. Zbiorczy druk oszczędza wyłącznie kliknięcia. To nie ten sam rodzaj korzyści.

**Rozpoznanie API zachowane, żeby nie powtarzać sondy, gdyby temat wrócił** (sprawdzone na sandboxie 2026-08-09):
- `GET /v1/organizations/{org}/shipments/labels?shipment_ids[]=…&format=pdf&type=normal`
- Same paczki JEDNEJ usługi → **jeden scalony PDF** (`application/pdf`). Mieszane → **ZIP z jednym scalonym PDF na usługę**. Nic nie trzeba sklejać po naszej stronie.
- **GOTCHA: `shipment_ids[]` MUSI być bez indeksów.** `Http::get($url, ['shipment_ids' => $ids])` buduje `shipment_ids[0]=`, a ShipX odpowiada wtedy `{"shipment_ids":["required"]}` — wygląda to na brak parametru, nie na zły format. Query trzeba złożyć ręcznie.
- Gdyby wracać: właściwym miejscem NIE jest ekran „Odbiór kuriera" (listuje tylko paczki dla kuriera, a drukować trzeba też te wrzucane do paczkomatu).
- Mail do `integracja@inpost.pl` **przestał być blokerem** — ma sens najwyżej jako potwierdzenie na piśmie.

**ZAMKNIĘTE 08.08:** typ konta produkcyjnego = **Firma krajowa** (potwierdził Rafał) → warunek z pkt 4.13 spełniony, temat regulaminowy domknięty · zlecenie odbioru kuriera — sprawdzone i działa (sekcja wyżej).

Powiązane: [[plan-shipping]] (cały moduł wysyłek, Poziom 1 i 2) · [[plan-packages]] (bramka `courier_shipping`) · [[shipping-aggregator-idea]] (Furgonetka — odłożona bezterminowo).
