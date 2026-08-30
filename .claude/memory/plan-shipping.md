---
name: plan-shipping
description: "Wysyłka w DWÓCH POZIOMACH (opcja bez integracji / etykiety z integracją). Kurier i PACZKOMAT z mapą WDROŻONE. NASTĘPNY DUŻY TEMAT: Poziom 2 = etykiety ShipX — plan ustalony 06.08, wdrażamy NA SANDBOXIE (paczka ~20 zł), produkcja dopiero na finalny test. Furgonetka odłożona."
metadata: 
  node_type: memory
  type: project
  originSessionId: d43b718a-bf6f-4d34-bb7d-d0b76a72a4c6
  modified: 2026-08-08T15:54:00.485Z
---

**USTALONE 2026-07-15 (rozmowa, bez kodu). Następny krok: mail do InPost, potem wdrożenie.**

## MODEL DWUPOZIOMOWY — sedno (pomysł Rafała)
Kluczowe rozdzielenie: **opcja dostawy ≠ integracja**. To NIE jest jedna funkcja.

- **Poziom 1 — każdy, ZERO integracji.** Sprzedawca zaznacza InPost, wpisuje koszt i (opcjonalnie) próg darmowej dostawy. Klient wybiera paczkomat, kod ląduje w zamówieniu, koszt dolicza się do sumy. Sprzedawca dostaje numer paczkomatu i **nadaje ręcznie** (albo „gołębiem pocztowym" — cytat Rafała). Działa od razu.
- **Poziom 2 — kto chce.** Sprzedawca wkleja tokeny ShipX → **etykieta do pobrania z panelu**.

**Why:** autor sprzedający 3–4 książki tygodniowo nigdy nie tknie integracji i NIE MUSI. Ten sam wzorzec, co w całym Kramio: `shop_integrations` opcjonalne, ustawienia sklepu — nie. **To samo dotyczy Furgonetki** (klient wybiera kuriera i podaje dane, sprzedawca może nadać ręcznie).

**Moja pomyłka, sprostowana przez Rafała:** twierdziłem, że wysyłka nie mieści się w obietnicy „sklep w 5 minut". Nieprawda — obietnica dotyczy drogi od rejestracji do MOŻLIWOŚCI sprzedaży, a wysyłka jest poza nią i nieobowiązkowa.

## ZAWIAS: token geowidget — OTWARTE, pytanie do InPost
Poziom 1 potrzebuje MAPY (klient musi wybrać paczkomat), mapa to geowidget, geowidget to token. **Jeśli token musi być sprzedawcy — poziom 1 przestaje istnieć**, bo żeby tylko pokazać mapę, każdy autor musiałby założyć konto InPost z danymi firmowymi i fakturowymi. Czyli dokładnie ta bariera, którą model miał obejść.

**Co wiadomo:** token generuje się w panelu InPost firmy razem z tokenem ShipX i Organization ID; **przy generowaniu wskazuje się DOMENĘ** (ślad: dokumentacja mówi, że dla sandboxa na localhost domeny NIE podaje się — więc normalnie się podaje). Czy wildcard `*.kramio.pl` przejdzie i czy wolno obsługiwać cudze sklepy pod swoim tokenem — **NIEUDOKUMENTOWANE**.

**Argument Rafała (mocny):** wszystkie sklepy siedzą pod `*.kramio.pl`, więc z punktu widzenia InPostu to **jedna domena i jedna strona — jego**. To nie jest platforma z cudzymi domenami. **Uwaga na przyszłość: własna domena (Pawilon, [[plan-packages]]) to psuje** — `sklep-anny.pl` wypada spod tokenu `kramio.pl`. Odłożenie domen gra tu na korzyść.

**Sprostowanie mojego wcześniejszego błędu:** mówiłem, że „geowidget może być nasz, wspólny, bo to tylko mapa". Nie ma podstaw — token jest per firma i per domena. Stąd pytanie do InPost.

**Odpowiedź AI Google (Rafał sprawdził 2026-07-15) — HIPOTEZA, nie ustalenie:** token wygenerowany dla domeny głównej `c.pl` ma automatycznie autoryzować wszystkie jej subdomeny (`a.c.pl`), bo walidacja idzie po nagłówku `Origin` (CORS); token innej domeny da 403. Brzmi wiarygodnie (dziedziczenie subdomen to standardowy wzorzec dla kluczy domenowych) i pasuje do tego, że przy generowaniu wskazuje się domenę — **ale to odpowiedź AI bez źródła, nie ma tego w dokumentacji InPostu.** Nie traktować jak faktu.

**KLUCZOWE ROZRÓŻNIENIE — Google odpowiedział na INNE pytanie:** to była odpowiedź o TECHNIKĘ (czy CORS przepuści subdomenę), a pytanie Rafała dotyczy UPRAWNIENIA (czy wolno mu jednym tokenem swojej firmy obsługiwać sklepy innych podmiotów). Mapa może ładować się bez zarzutu i i tak zostaje kwestia regulaminowa. Ryzyko materializuje się w najgorszym momencie: gdy InPost odetnie token i mapa padnie **wszystkim sklepom naraz**. Dlatego mail zostaje, ale z precyzyjnym pytaniem — nie „czy zadziała na subdomenie", tylko **„czy wolno tym tokenem obsługiwać sklepy moich klientów"**.

## Sandbox — JEST (zweryfikowane 2026-07-15)
- API: `sandbox-api-shipx-pl.easypack24.net`; rejestracja: `sandbox-manager.paczkomaty.pl` → Moje konto → API.
- **Osobne konto** (dane produkcyjne nie działają). Trzeba uzupełnić dane firmy + fakturowe, by wygenerować token. Konto doładowuje się **wirtualnie** (zakładka Płatności) — testy za darmo.
- Ograniczenia: **tracking wyłączony**; dokumentacja wspomina o ograniczeniach przy samym nadaniu (brzmi niejednoznacznie — zweryfikować w praktyce).

## Fakty techniczne
- **Geowidget v5 = gotowy custom element.** Cały kod i przetwarzanie **na serwerach InPost** — wpinamy skrypt w `<head>` + element z tokenem. Ma PL/UA/EN i WCAG za darmo. **Rafał ma rację: strona klienta to mało pracy; ciężar jest w panelu sprzedawcy.** Uwaga przy wdrożeniu: to JS widget w świecie Livewire — pilnować re-renderów.
- **Etykieta jest ASYNCHRONICZNA.** ShipX: utwórz przesyłkę → InPost przetwarza → dopiero po statusie `confirmed` da się pobrać PDF. To NIE jest „kliknij i masz". Realnie: przycisk „Nadaj przesyłkę" → tworzenie w tle → „Pobierz etykietę" zapala się po potwierdzeniu. Wzorzec: outbox + cron ([[email-outbox-cron-pattern]]).
- **Sprzedawca wkleja TRZY wartości** z panelu InPost: token ShipX, token Geowidget, Organization ID. Wszystkie wymagają uzupełnionych danych firmowych i fakturowych.

## Ustalenia produktowe
- **Darmowa dostawa = OPCJONALNY próg.** Sprzedawca zaznacza i podaje kwotę (np. od 500 zł) albo nie zaznacza — wtedy darmowej nie ma.
- **Instrukcja zakładania konta InPost w panelu sprzedawcy — WYMAGANA** (pomysł Rafała, potwierdzony researchem). Amator musi: założyć konto, uzupełnić dane firmy I dane do faktury, wygenerować TRZY różne wartości. Bez instrukcji krok po kroku większość się odbije.
- Kolejność: **wysyłka przed płatnościami online** — wysyłka jest niezależna (działa z przelewem tradycyjnym, który jest zrobiony), a płatności to osobny worek (operator, prowizje, webhooki, zwroty).
- Rafał: robić „kompleksowo od strony klienta"; obie wysyłki (InPost + Furgonetka) razem albo jedna po drugiej — **to bliźniacze rozwiązania**.

## Stan kodu (zweryfikowany 2026-07-15) — fundament CZEKA
- `App\Enums\DeliveryMethod` — tylko `Pickup`. Ma `isShipped()`, a komentarz wprost przewiduje: „Kolejne (kurier, dostawa własna) dojdą jako nowe case'y + konfiguracja per-sklep" oraz próg darmowej dostawy. **Miejsce wycięte i czeka.**
- `orders`: **`delivery_method` i `delivery_cost` JUŻ SĄ** (migracja `2026_07_03_150100_create_orders_table`).
- `shop_integrations`: `shop_id` + `type` + `enabled` + **`config` (encrypted:array)** + unique(shop_id, type). `IntegrationType` mówi wprost: „Kolejne (PayU, InPost, …) dokładamy jako nowe case'y — bez migracji". **Szyfrowany config per sklep = architektura już zakłada, że sprzedawca wkleja własne tokeny.**
- **BRAKUJE:** pola na kod paczkomatu i dane punktu w `orders`; konfiguracji dostawy per sklep (włączone + koszt + próg); case'ów w `DeliveryMethod`.

## ODPOWIEDŹ INPOSTU — PRZYSZŁA 2026-07-17. Zawias zdjęty w połowie.
**Mail dział: `integracja@inpost.pl` (Wsparcie Techniczne ds. Wdrożeń Aplikacji IT) — DZIAŁA, odpisali w dobę.** Koryguje wcześniejszą notatkę „InPost nie podaje publicznego maila" (nieprawda) i `integracja@grupainteger.pl` (przestarzały o jedną nazwę holdingu). Droga wejścia: formularz `https://inpost.pl/formularz-wsparcie` → „Udzielenie dostępu do API".

**Co odpowiedzieli:**
1. **Technika — plan B podany na tacy:** „proponuję wygenerować token do Geowidget **bez podawania URL sklepu** (powstanie uniwersalny klucz)" — wtedy nie ma walidacji `Origin` i problem subdomen znika. Hipoteza Google o dziedziczeniu subdomen staje się bezprzedmiotowa (omijamy mechanizm).
2. **Uprawnienie — PUDŁO:** na pytanie o ścieżkę dla platform SaaS: **„tego nie wiemy"** + link do landingu reklamowego abonamentów z Google Ads (`inpost.pl/abo`, pełen `utm_`/`gclid`). To lejek sprzedażowy, nie program partnerski. Potwierdziło się rozróżnienie technika-vs-uprawnienie: odpowiedzieli na pierwsze, drugie zostaje OTWARTE i nikt go na piśmie nie zamknie.

**Haczyk klucza uniwersalnego (nie napisali):** token geowidget siedzi jawnie w HTML strony (jest publiczny z natury). Token związany z domeną jest chroniony przez `Origin` — podebrany nie zadziała gdzie indziej. **Uniwersalny tej ochrony nie ma z definicji** — każdy może go zdjąć z podglądu źródła i wpiąć u siebie. Optyka regulaminowa też gorsza: „mam token na swoją domenę" broni się lepiej niż „wyłączyłem sprawdzanie domeny".

## PLAN (ustalony z Rafałem 2026-07-17) — konto PRODUKCYJNE + token na kramio.pl
**Rafał: zakładamy konto od razu produkcyjne, nie sandbox** — sandbox to osobne konto, znów dane firmowe+fakturowe, tracking wyłączony i niepewna walidacja; podwójna robota dla słabszego sygnału. Konto produkcyjne i tak jest potrzebne, daje wszystkie TRZY wartości naraz (ShipX + geowidget + Organization ID) → odblokowuje Poziom 1 (mapa) I Poziom 2 (etykiety). Załadowanie mapy nic nie kosztuje i niczego nie nadaje, więc test tokenem produkcyjnym jest bezpieczny.

**Test:** token związany z `kramio.pl` → goła strona testowa na `ilikemybike.kramio.pl` (sam skrypt InPostu + element widgetu, **bez Livewire** — re-rendery zamazują obraz) → konsola pokazuje mapę albo 403.
- **Mapa → bierzemy token związany z domeną.** Lepszy od uniwersalnego na obu osiach (zachowuje ochronę `Origin` + lepsza optyka). To argument Rafała z tej notatki: wszystkie sklepy pod `*.kramio.pl` = z punktu widzenia InPostu jedna domena, jego.
- **403 → plan B = klucz uniwersalny**, już pobłogosławiony przez wsparcie.
- Kontrola negatywna (ten sam token na obcej domenie) **ŚWIADOMIE ODRZUCONA** przez Rafała — nie zmienia decyzji w żadnej gałęzi, bo token związany bierzemy tak czy siak. Patrz [[feedback-dont-stack-caveats]].

**Hedge architektoniczny (do wdrożenia):** `shop_integrations` już zakłada, że sprzedawca wkleja własny token geowidget → hierarchia **token sprzedawcy, jeśli wkleił; nasz jako zapasowy**. Sprzedawcy z Poziomu 2 sami schodzą nam z linii ognia. Do tego fallback UX: gdy widget nie wstanie, klient musi móc **wpisać kod paczkomatu z palca** — padnięcie mapy = niewygoda, nie zatrzymany sklep.

**Do sprzedaży (`inpost.pl/abo`) wracamy PÓŹNIEJ** — przy zerowej skali nie ma czym negocjować; rozmowa ma sens, gdy paczkomat działa i są liczby.

**Uwaga kontrariańska (odnotowana raz, nie blokuje):** „wielka firma z mocnym zapleczem" przemawia raczej za ŚCISŁYM dopasowaniem `Origin` niż luźnym — dokładne porównanie origin to bezpieczna domyślka, dziedziczenie subdomen trzeba świadomie dopisać. Czyli nie zakładać z góry, że subdomena przejdzie. Konsola rozstrzygnie w minutę.

**O co zapytaliśmy:** o UPRAWNIENIE, nie technikę — czy token geowidget wystawiony na firmę Red Paprika / domenę `kramio.pl` może obsługiwać sklepy KLIENTÓW platformy na subdomenach `*.kramio.pl`. Plus: czy da się wskazać wildcard, i czy jest ścieżka dla platform SaaS (a jeśli model jest niedopuszczalny — jaka droga zalecana). W treści opisane OBA scenariusze celowo: pokazuje, że nie obchodzimy integracji, tylko dajemy łagodne wejście.

**Weryfikacja dokumentacji 2026-07-16:** Geowidget v5 zawiera dokładnie JEDNO zdanie o domenie — „dla sandboxa na localhost nie należy wskazywać domeny podczas generowania tokenu" — i **ani słowa o subdomenach czy wildcardzie**. Hipoteza AI Google (dziedziczenie subdomen po Origin) nadal bez potwierdzenia w źródle.

## WDROŻENIE KURIERA — Poziom 1, przewoźnik-agnostyczny (start 2026-07-16)
Ruszyliśmy od **kuriera „pod adres"**, bo to jedyny kształt z ZEROWĄ zależnością od InPostu (bez mapy/geowidgetu/tokenu — zawias nie dotyczy). Paczkomat „do punktu" (mapa) = osobny, późniejszy klocek na tym samym szkielecie. Robimy MAŁYMI krokami z przystankami (Rafał ma ograniczony czas w ciągu dnia).

**Odkrycia z kodu (głębszy fundament, niż notowałem):**
- `orders` MA JUŻ kolumny adresu wysyłki `ship_street/ship_building_number/ship_apartment_number/ship_postal_code/ship_city` (dziś puste przy odbiorze) — model adresu istnieje.
- `OrderTotals` już liczy `total_gross = produkty + delivery_cost`; dziś `OrderService` zaszywa `delivery_cost=0`.
- Statusy: scenariusz 3 (przelew+wysyłka) rozpisany w [[plan-order-statuses]] — `Oczekuje na płatność → Opłacone → W realizacji → Gotowe do wysyłki → Zrealizowane`, BEZ „Wysłane". `OrderStatus::ReadyForShipment` trzeba DODAĆ (enum go nie ma; `Shipped` był usunięty).
- **Kurier chodzi TYLKO z przelewem** (`PayOnPickup`=„płatność przy odbiorze" nie ma sensu bez odbioru; pobrania w modelu nie ma). W kasie: wybór kuriera musi ukryć „Płatność przy odbiorze".
- Próg darmowej dostawy liczymy od `items_total` (produkty, brutto), przed kosztem dostawy.

**KROK 1 — PANEL: ZROBIONE 2026-07-16 (587 testów, niezacommitowane w chwili pisania).**
- Migracja `2026_07_16_100000_add_courier_delivery_to_shops_table`: `courier_enabled` (bool), `courier_cost` (decimal nullable), `courier_free_from` (decimal nullable, NULL=brak progu).
- `Shop`: fillable+casts; `courierAvailable()` = sam włącznik (BEZ bramki adresu — kurier „działa od razu"); `courierCostFor(float $itemsGross)` (0 gdy `free_from` osiągnięty, inaczej koszt).
- `ShopSettingsRequest`: `courier_enabled` bool; `courier_cost` required-if-enabled (0 dozwolone=gratis); `courier_free_from` opcjonalny; normalizacja „19,90"→„19.90" (`normalizeAmount`, wzorzec z ProductRequest).
- Widok `seller/settings/edit.blade`: karta „Dostawa kurierem" w sekcji Dostawa (włącznik + koszt + „darmowa dostawa od"), miękki hint gdy przelew wyłączony. `sm:pl-9` NIE istnieje w buildzie — usunięte (patrz [[tailwind-classes-must-exist-in-build]]).
- Testy: 6 nowych w `ShopSettingsTest`.

**KROK 2 (KASA) + KROK 3 (ZAPIS+WIDOKI): ZROBIONE 2026-07-16 (593 testy).**
- `DeliveryMethod::Courier` (isShipped=true), `OrderStatus::ReadyForShipment` („Gotowe do wysyłki", plakietka reużywa `violet` — odbiór i wysyłka nigdy razem), `OrderFlow` handover dla `Courier`.
- `Checkout` (Livewire): kurier w `deliveryOptions()`; pola `ship_*` (adres wymagany tylko przy `shippedDelivery()`); `paymentOptions()` zdejmuje „płatność przy odbiorze" gdy wysyłka; `updatedDeliveryMethod()` przełącza płatność na pierwszą dostępną gdy bieżąca wypadła; `wire:model.live` na wyborze dostawy; koszt + wiersz „Dostawa" w podsumowaniu.
- `OrderService::createOrder`: liczy `delivery_cost` (`courierCostFor(itemsGross)`) PRZED `recalculate` (bo total_gross = items + delivery_cost), zapisuje `ship_*` przez `shipField()` (null przy odbiorze).
- Widoki: potwierdzenie + konto klienta = wiersz „Dostawa"/„Gratis" + kurierski adres; mail `deliveryBlock` = adres dostawy + koszt; panel sprzedawcy (order-editor + orders/show) MIAŁ JUŻ wiersz kosztu i adres (`isShipped()`), zbudowane wcześniej.
- Testy: `OrderPlacementTest` (koszt+adres, próg gratis, adres pomijany przy odbiorze) + `CheckoutTest` (kurier wymaga adresu, kurier zdejmuje pay_on_pickup, e2e gość składa z kosztem+adresem).

## PACZKOMAT — WDROŻONY CAŁY 2026-07-17 (5 kroków, 670 testów, commit `5278e47`). Mapa POTWIERDZONA OKIEM Rafała w przeglądarce („DZIAŁA SUPER”). Zawias tokenu `*.kramio.pl` ostatecznie zamknięty.
Bliźniak kuriera + mapa. Token geowidget na `*.kramio.pl` w `.env` (`INPOST_GEOWIDGET_TOKEN`, JWT, `allowed_referrers: *.kramio.pl`, ważny do 2036, scope `api:apipoints`). **Decyzja Rafała 2026-07-17: TYLKO paczkomaty, PoP na razie NIE.**

- **Krok 1 (panel):** `shops.parcel_locker_enabled/_cost/_free_from` (migracja `2026_07_17_120000`); `Shop::parcelLockerAvailable()`/`parcelLockerCostFor()`; karta „Paczkomat InPost" w Ustawieniach obok kuriera; `ShopSettingsRequest`.
- **Krok 2 (model):** `DeliveryMethod::ParcelLocker`. **Rozdzielono ZROST pojęć w enumie:** `isShipped()` = „to wysyłka, ma koszt" (kurier+paczkomat), `requiresShippingAddress()` = tylko kurier (paczkomat jedzie do skrytki, NIE pod adres), `requiresParcelLocker()` = tylko paczkomat. `Shop::deliveryCostFor($method,$gross)` + `deliveryFreeFrom($method)` = JEDNO źródło cennika (match bez default → nowa metoda wywali UnhandledMatchError). `orders.parcel_locker_code/_address` (migracja `2026_07_17_130000`), wyklucza się z `ship_*`.
- **Krok 3 (kasa):** opcja dostawy + pole kodu (z palca, normalizacja do wersalików), zdejmuje „płatność przy odbiorze", podpowiedź z ostatniego zamówienia, walidacja kodu LUŹNA (`[A-Z0-9]+`, bo InPost nie gwarantuje formatu — zbyt ostra reguła blokuje zakup).
- **Krok 4 (widoki+maile):** kod paczkomatu zamiast bloku adresu w: mailu KLIENTA (`deliveryBlock`), potwierdzeniu, koncie klienta, panelu sprzedawcy (`orders/show`). Mail SPRZEDAWCY celowo NIE niesie kodu (jak przy kurierze nie niesie adresu) — patrz niżej „otwarte".
- **Krok 5 (mapa):** geowidget v5. Skrypt+CSS `https://geowidget.inpost.pl/inpost-geowidget.{js,css}` warunkowo w `@stack('head')` (layout storefrontu), tylko gdy sklep ma paczkomat+token. Element `<inpost-geowidget token config="parcelCollect" onpoint="afterInpostPointSelected">` w `wire:ignore` (chroni przed re-render Livewire), Alpine most przez `window` CustomEvent → `$wire.set`. **TYLKO paczkomaty przez `api.changePointsType('PARCEL_LOCKER')`** po evencie `inpost.geowidget.init` — atrybut `config` sam NIE umie odfiltrować PoP (każda wartość miesza Parcel Locker + ParcelPoint). `FilterPointsType = ALL|PARCEL_LOCKER|POP`. Fallback: bez tokenu pole kodu + link do mapy InPostu.

**ZWERYFIKOWANE OKIEM 2026-07-17:** widget wstaje na subdomenie, filtruje do paczkomatów, oddaje wybór. Token `*.kramio.pl` działa (formularz InPostu SAM podpowiedział `*.kramio.pl` przy generowaniu). UX-owa iteracja po pierwszym pokazie: mapa inline była za mała → przeniesiona do POPUP-a (fixed overlay, h-70vh, Escape/tło/× zamyka, widget montowany przez `x-if` dopiero przy otwarciu — display:none łamie rozmiar mapy); przycisk „Wybierz na mapie” przeniesiony OBOK pola kodu.

**HEDGE token per-sklep — DO ZROBIENIA:** kasa bierze token z `config('services.inpost.geowidget_token')` (platformowy). Docelowo sprzedawca z własnym kontem InPost wkleja swój do `shop_integrations` i ma PIERWSZEŃSTWO; nasz zapasowy. Komentarz-ślad w `Checkout::render()`.

## POZIOM 2 — ETYKIETY ShipX. PLAN USTALONY 2026-08-06 (rozmowa, bez kodu). NASTĘPNY DUŻY TEMAT.

**Rafał: „spora część wysyłek to InPost" — automatyczne nadawanie to dobre wyjście na start platformy.**

### DECYZJA: NAJPIERW SANDBOX (Rafał 2026-08-06)
**To NIE jest odwrócenie decyzji z 17.07 („konto od razu produkcyjne, nie sandbox") — tamta dotyczyła TOKENU GEOWIDGET, czyli mapy, która nic nie kosztuje i niczego nie nadaje.** Tu każde nadanie to REALNA paczka ~20 zł; przy iteracyjnym wdrażaniu uzbiera się kilkanaście. Stąd:
1. Całość wdrażamy i testujemy na sandboxie (`sandbox-api-shipx-pl.easypack24.net`, rejestracja `sandbox-manager.paczkomaty.pl` → Moje konto → API; osobne konto, dane firmowe+fakturowe, saldo doładowywane WIRTUALNIE = testy za darmo).
2. Po komplecie testów przepięcie na produkcję (`api-shipx-pl.easypack24.net`) + JEDEN realny test za ~20 zł — Rafał wyśle paczkę do siebie albo do znajomego.
3. Konsekwencja architektoniczna: **base URL musi być przełączalny z `.env`** (jak przy każdej integracji), a sandbox ma ograniczenia (m.in. tracking wyłączony) — nie panikować, gdy czegoś nie da się sprawdzić przed produkcją.

### Przepływ docelowy (opisany Rafałowi 06.08, zaakceptowany)
Klient NIE widzi różnicy przy zakupie — zmienia się wyłącznie robota sprzedawcy PO zamówieniu. „Przepisz dane do panelu InPostu i wydrukuj" → „kliknij Nadaj, wydrukuj".
1. Jednorazowo: sprzedawca wkleja w Integracjach **token ShipX + Organization ID** (szyfrowane per sklep w `shop_integrations`, jak Fakturownia/Paynow). Wymaga własnej umowy z InPostem i zasilonego salda.
2. Na zamówieniu przycisk **„Nadaj przesyłkę"** + wybór GABARYTU (A/B/C).
3. My w tle: `service: inpost_locker_standard`, `custom_attributes.target_point` = `orders.parcel_locker_code`, odbiorca = imię/e-mail/**telefon** (telefon OBOWIĄZKOWY — SMS z kodem odbioru; **zweryfikowane 06.08: kasa ma `buyer_phone` jako `required`**, więc komplet danych JEST).
4. **Nadanie jest ASYNCHRONICZNE** (create → wycena → zakup z salda → status `confirmed`). Panel: „Nadawanie…" → cron dopytuje → „Nadana" + numer przesyłki + **„Pobierz etykietę (PDF)"**. Wzorzec: [[email-outbox-cron-pattern]].
5. Oddanie paczki: sprzedawca wrzuca sam do paczkomatu ALBO zamawia kuriera po odbiór (*zlecenie odbioru* / dispatch order — też w API, wart osobnego przycisku).
6. **Numer do śledzenia dla klienta** (mail o zmianie statusu + konto klienta) — realna wartość, dziś klient widzi tylko „gotowe do wysyłki".
7. Statusy InPostu (webhook/odpytywanie) → docelowo automatyczne przejście na „Zrealizowane" po odbiorze paczki ([[vision-email-driven-orders]]).

### PRÓBA GENERALNA NA SANDBOXIE 2026-08-07 — przepływ przejechany RĘCZNIE curl-em, zanim powstał kod
Konto sandbox: organizacja **6700** (`rafal@kociaczek.com.pl`), token z `sandbox-login.inpost.pl`. Produkcyjne: organizacja **203242**.
**GOTCHA przy zakładaniu: sandbox to OSOBNA rejestracja — Rafał za pierwszym razem wygenerował token na `manager.paczkomaty.pl` (produkcja). Rozpoznanie po `iss` w JWT: `login.inpost.pl` = produkcja, `sandbox-login.inpost.pl` = sandbox.**

Co potwierdzone empirycznie (te fakty > dokumentacja):
1. `GET /v1/points?type=parcel_locker` — działa; **sandbox ma WŁASNE, fikcyjne paczkomaty** (nazwy typu „60000", „A5337537582"), kody produkcyjne (KRA01A) tam nie istnieją. Testowe zamówienie musi nieść sandboxowy kod.
2. `POST /v1/organizations/{org}/shipments` z `service: inpost_locker_standard`, `parcels:[{template:"small"}]`, `custom_attributes:{target_point, sending_method:"parcel_locker"}` → **201, status `created`**, po chwili sam przechodzi na **`offer_selected`** (tryb uproszczony sam wybiera ofertę).
3. `POST /v1/shipments/{id}/buy` z **pustym body → 400 `validation_failed` (`offer_id: required`)**. Trzeba przekazać `offer_id` ze `selected_offer.id`.
4. **NAJWAŻNIEJSZE: nieudany zakup NIE zgłasza się błędem.** `buy` z poprawnym `offer_id` zwraca **200 i status dalej `offer_selected`**, a prawdziwa przyczyna leży w tablicy **`transactions[].details`** (u nas: `{"status":422,"error":"debt_collection"}` = brak środków). **Kod MUSI czytać `transactions`, inaczej przesyłka wisi w `offer_selected` w nieskończoność i nikt nie wie czemu.** To jest ta „najczęstsza wpadka sprzedawcy" (brak salda) — przewidziana w planie, potwierdzona w praktyce.
5. `GET /v1/shipments/{id}/label?format=pdf&type=normal` przed opłaceniem → **400 `invalid_action` / `shipment_status_incorrect`** z podanym bieżącym statusem. Czytelny komunikat, dobry do mapowania na UI.
6. **Sandbox potrafi zwrócić 404 na ISTNIEJĄCĄ przesyłkę** przy kilku szybkich zapytaniach pod rząd (nie 429 — właśnie 404). Odpytywanie statusu NIE może traktować pojedynczego 404 jako „przesyłka zniknęła" — potrzebna tolerancja/ponowienie.
7. Oferta ma `expires_at` (~5 min od utworzenia) — zakup po czasie będzie wymagał nowej oferty.

**PRÓBA DOKOŃCZONA 2026-08-07 po doładowaniu salda (500 zł wirtualnych) — CAŁA ŚCIEŻKA DZIAŁA:**
8. **Przy ZASILONYM koncie zakup dzieje się SAM.** Nowa przesyłka poszła `created` → `offer_selected` → **`confirmed`** bez naszego udziału; jawne `POST /buy` zwróciło wtedy **400** (bo już kupiona). Wniosek dla kodu: **nie zakładać, że musimy wołać `buy`** — odpytywać status, a ewentualne `buy` traktować defensywnie (400 „już kupiona" to NIE jest błąd). Wcześniejsze `debt_collection` blokowało automat; po doładowaniu automat ruszył.
9. `tracking_number` pojawia się przy statusie `confirmed` (u nas: `642582187600000017868961`).
10. `GET /v1/shipments/{id}/label?format=pdf&type=normal` → **200, `application/pdf`, ~22 KB, realna etykieta** (nadawca, kod paczkomatu, gabaryt). Gotowa do druku.
11. **`GET /v1/tracking/{numer}` DZIAŁA w sandboxie** (wbrew notatce „tracking wyłączony" z 07-15 — to było o czymś innym albo nieaktualne). Zwraca status + `custom_attributes` z `size: "A"`, `target_machine_id`, `dropoff_machine_id: "ANY-PM"` (= można wrzucić do dowolnego paczkomatu nadawczego).
12. `parcels:[{template:"small"}]` = **gabaryt A** (potwierdzone na etykiecie i w trackingu). Czyli mapowanie: small→A, medium→B, large→C.

**Usługi na koncie (oba środowiska tak samo): `inpost_locker_standard` JEST (to nasza ścieżka). Zwykłego kuriera biznesowego `inpost_courier_standard` NIE MA** — tylko warianty allegrowe i `inpost_courier_c2c`. Etykiety kurierskie przez InPost wymagałyby rozszerzenia umowy; paczkomat działa bez przeszkód.

### WDROŻONE 2026-08-07 — Poziom 2 DZIAŁA (sandbox, potwierdzone okiem Rafała: „wszystko jest super")
Cztery kroki z przystankami, ~30 testów w `tests/Feature/Shipping/`.

- **Krok 1 — integracja:** `IntegrationType::Shipping`; `config/services.php` → `inpost.shipx.base_url.{sandbox,production}`; `Shop::shipxToken/OrganizationId/Environment/Configured/Enabled/BaseUrl()`; karta w Integracjach (token jako `password`, Organization ID jawne, checkbox „Konto testowe (sandbox)", rozwijana INSTRUKCJA 5 kroków); włącznik w Ustawieniach. Bramka pakietu = **istniejące `courier_shipping`** (nie nowy klucz — lepkie snapshoty).
  - **Rozłączenie idzie przez Organization ID, NIE token** — token jest sekretem (pole zawsze puste = „zostaw"), więc gdyby on decydował, każdy zapis kasowałby integrację.
- **Krok 2 — klient:** `App\Services\Shipping\ShipxClient` (createShipment / shipment / label / isReady / failureReason) + `App\Enums\ParcelSize` (small/medium/large = gabaryt A/B/C, z wymiarami i podpowiedzią) + kanał logu `shipx` (365 dni, bez tokenu).
- **Krok 3 — panel:** migracja `2026_08_07_180000` (`shipment_id`, `shipment_tracking_number`, `shipment_status`, `shipment_size`, `shipment_error`, `shipped_at`); `Order::hasShipment/isShipmentReady/isShipmentPending/canBeShipped/requestShipment/trackingUrl()`; job `CreateInpostShipment` (`tries=1`, guard `hasShipment`); komenda `shipments:refresh` co minutę; Livewire `Seller\OrderShipment` + trasa `seller.orders.label` (PDF przez NASZ serwer — token nie opuszcza backendu).
- **Krok 4 — dopieszczenie:** potwierdzenie w miejscu z WYBRANYM GABARYTEM i paczkomatem (bliźniak potwierdzenia zmiany statusu); etykieta w nowej karcie; w sandboxie ukryty przycisk śledzenia + wyjaśnienie (numery testowe nie istnieją w wyszukiwarce InPostu — inaczej wygląda to jak NASZA awaria).

**GOTCHY z wdrożenia (kosztowały czas, nie powtarzać):**
- **Stan „w trakcie" NIE może opierać się na `shipment_id`** — ten pojawia się dopiero po wykonaniu zadania (kolejka = cron, do minuty), więc przycisk wracał do postaci wyjściowej i nic się nie odświeżało („kliknąłem i nic"). Rozwiązanie: własny status `Order::SHIPMENT_QUEUED` ustawiany PRZED `dispatch` (jak `markInvoicePending` przy FV).
- **Ponowienie po błędzie MUSI kasować `shipment_id`**, inaczej guard `hasShipment()` w jobie blokuje drugą próbę na zawsze. Bezpieczne, bo błąd = przesyłka nieopłacona.
- **Telefon do InPostu bez prefiksu `+48`** (my trzymamy znormalizowany z prefiksem, ShipX chce 9 cyfr).
- Utknięte `queued` (padła kolejka) odblokowuje `shipments:refresh` po 15 min — bez tego wieczne „Nadajemy…".
- **Test-gotcha (nie błąd kodu):** `actingAs` trzyma TEN SAM obiekt użytkownika między żądaniami testu, więc relacja `integrations` załadowana przy wcześniejszym GET jest nieświeża przy POST → użyć `actingAs($seller->fresh())`.
- Długi link w mailu (token zwrotu) rozpychał wiadomość → `MailMarkup` umie teraz `[tekst](url)` (tylko http/https) + `word-break` w akapitach maila.

### DOMKNIĘTE tego samego dnia (commity `015a81e`…`e9ee38b`, testy 1339 → 1400)
- **DATA ODBIORU (`orders.delivered_at`)** — zapisywana automatycznie, gdy InPost zgłosi doręczenie (dla paczkomatu = klient wyjął paczkę). **Od niej liczymy DOKŁADNIE 14 dni na odstąpienie**; bez niej po staremu (realizacja + zapas). Pomysł Rafała.
- **DWA BŁĘDY PRAWNE ZNALEZIONE PRZEZ RAFAŁA (ważne!):**
  1. `withdrawalDeadline()` liczył od DATY ZŁOŻENIA, gdy brakowało statusu „Zrealizowane" → przy rękodziele robionym tygodniami termin „mijał" przed dostawą i **zamykał formularz zwrotu klientowi, któremu prawo dopiero zaczynało biec**. Teraz: brak realizacji = termin NIE wystartował (metoda zwraca `null`, okno otwarte).
  2. Formularza zwrotu nie wolno wysłać przed wydaniem towaru → nowe `Order::hasBeenHandedOver()` (delivered_at LUB status Completed) bramkuje `acceptsReturns()`. **Rozróżnienie: PRAWO istnieje od zawarcia umowy, ale FORMULARZ (pomniejsza zamówienie, mówi „odeślij") ma sens dopiero po wydaniu.** Przed wydaniem: karta „Zwrot" w koncie klienta i tak WIDOCZNA, tłumaczy kiedy się otworzy + kontakt do sklepu.
- **Karta „Odstąpienie od umowy"** w panelu sprzedawcy (pod „Kupujący") — ZAWSZE gdy jest towar objęty prawem, niezależnie od InPostu; z licznikiem dni i informacją, czy data jest dokładna czy szacowana.
- **Dwa maile do klienta:** „Paczka w drodze" (po nadaniu — numer, paczkomat; przycisk śledzenia TYLKO na produkcji) i „Dziękujemy za zakupy" (po odbiorze — data graniczna + „Zgłoś zwrot", link tylko gdy coś podlega zwrotowi). Oba wysyłane RAZ (wykrywanie przejścia przed zapisem).
- **Profilaktyka:** indeks `orders.shipment_id` (cron co minutę przestał skanować tabelę), kolumna `shipment_queued_at` (zamiast `updated_at`, który podbija każda edycja), `MailMarkup` obsługuje `[tekst](url)` + `word-break` w mailach (długi token zwrotu rozpychał wiadomość).
- **`ShipxTokenNeverLeaksTest`** — strażnik: token nie może pojawić się w HTML panelu, storefrontu, kasy, konta klienta ani nagłówkach etykiety; w bazie leży zaszyfrowany. **ZWERYFIKOWANY przez wstrzyknięcie sztucznego wycieku — zapalił się na czerwono.** Tak testować każdy strażnik bezpieczeństwa.

**ZOSTAŁO przy tym module:** nadawanie zbiorcze (jeden PDF z wieloma etykietami — **Rafał 07.08: ODPUSZCZONE do czasu realnego ruchu**); zlecenie odbioru kuriera (dispatch order — jeden przycisk na gotowym kliencie); ewentualne pominięcie maila statusowego, gdy w tej samej minucie poszedł mail o nadaniu. **Finalny test produkcyjny za ~20 zł — po decyzji Rafała.**
**NIE ROBIMY:** automatycznego przestawiania statusu zamówienia na „Zrealizowane" po odbiorze — **decyzja Rafała 07.08: status to deklaracja SPRZEDAWCY o tym, co zrobił.**

**NIE ROBIMY (2): wyciszania maili statusowych przy nadaniu paczki.** Zmierzone: pełna ścieżka paczkomatowa = 7 maili do klienta, w tym trzy o zbliżonej treści („Gotowe do wysyłki" / „Paczka w drodze" / „Zrealizowane"). Zaproponowałem regułę pomijania — **Rafał 07.08 ODRZUCIŁ, z dobrym uzasadnieniem:** (a) w teście zmieściły się w 13 minut, ale realnie sprzedawca obsługuje klienta cały dzień, kurier bywa wieczorem, a domknięcie zamówienia idzie na następny dzień; (b) **InPost wysyła WŁASNE 2 maile** (paczka w paczkomacie + kod odbioru), więc przy kurierze tych dwóch nie ma — bilans jest inny dla każdej metody dostawy, a nasze maile wypełniają lukę tam, gdzie przewoźnik milczy. Nie wracać do tematu bez sygnału od realnych klientów.

### Do zbudowania
Klient ShipX (przesyłka / etykieta / statusy / zlecenie odbioru) · nowy `IntegrationType` + ekran z **instrukcją krok po kroku** (KRYTYCZNE: amator odbija się od panelu InPostu, nie od naszego) · kolumny w `orders` (id przesyłki, numer, status, gabaryt) · przycisk + praca w tle + cron · opcjonalnie **nadawanie zbiorcze** (zaznacz N zamówień → jeden PDF z etykietami) — killer feature przy ruchu.

### Otwarte przy wdrożeniu
- **Gabaryt**: domyślny per sklep (skłaniam się) vs pytanie przy każdym nadaniu.
- **Pieniądze są SPRZEDAWCY** — nadanie zdejmuje z jego salda InPost, Kramio nie pośredniczy (spójne z „SaaS, nie marketplace" z regulaminu §4). Czytelnie obsłużyć błąd „brak środków" — to będzie najczęstsza wpadka.
- Hedge tokenu geowidget per sklep (niżej) można domknąć przy okazji — sprzedawca z kontem InPost i tak wkleja swoje dane.

## KURIER POD ADRES PRZEZ INPOST — UDOWODNIONE 2026-08-08 → pełna notatka: [[plan-inpost-courier]]
Pomysł Rafała: zamiast planować Furgonetkę, po prostu spróbować nadać kurierem. Sonda API na sandboxie przejechała **całą ścieżkę: utworzenie → oferta → zakup → `confirmed` → numer → etykieta PDF**. Usługa to `inpost_courier_c2c`, brakującym polem było `custom_attributes.sending_method`, a **tryb uproszczony działa tak samo jak przy paczkomacie — istniejący job i cron nie wymagają zmian**. Bloker regulaminowy zdjęty regulaminem Managera Paczek (pkt 4.10–4.13: podział po TYPIE KONTA, nie po nazwie usługi).

**Wszystkie szczegóły — payload, jednostki, sposoby nadania, plan wdrożenia, konta — w [[plan-inpost-courier]]. Czytać to PRZED pisaniem kodu.**

**ZOSTAŁO (dalej):**
- **Furgonetka — WYPADA Z PLANU (Rafał, 2026-08-08):** „jeśli to wyjdzie, zapominamy o Furgonetce i kolejnych umowach sprzedawcy". Skoro InPost umie kuriera pod adres na koncie, które sprzedawca już ma, broker przestał rozwiązywać jakikolwiek problem. Wcześniejsza analiza brokerów (Furgonetka / Apaczka / Sendit / Sendcloud) → [[shipping-aggregator-idea]]; wracać tylko, gdyby InPost odpadł.
- **Poziom 2 InPost** = etykiety (ShipX, async, outbox+cron).
- Rozważyć: czy mail SPRZEDAWCY ma nieść kod paczkomatu/adres, żeby nadawał bez wchodzenia w panel (kierunek [[vision-email-driven-orders]]).
- Link szybkiego wypisu z mailingu (patrz [[next-marketing-consent]]) osobno.

**NASTĘPNY DUŻY TEMAT (po wysyłkach): płatności online — patrz [[plan-online-payments-mbank]].**

## Od czego zacząć wdrożenie (nie czekamy na odpowiedź)
Ustawienia dostawy per sklep → model danych (kod paczkomatu w `orders`) → kasa. **Mapa to ostatni klocek i NIE blokuje startu** — wszystko poza nią robi się bez odpowiedzi InPostu.

Powiązane: [[plan-packages]] (wysyłki = pakiety płatne, InPost+Furgonetka razem), [[shipping-aggregator-idea]] (broker = Furgonetka), [[bank-transfer-payment-method]], [[plan-shop-settings-storage]].
