---
name: plan-packages
description: "USTALONE (2026-06-30) — model pakietów SaaS: 3 tiery 24/48/96, abonament roczny, nadpisania uprawnień per sklep; billing i wykonanie później."
metadata: 
  node_type: memory
  type: project
  originSessionId: 496eb5d0-33a2-486c-9a6d-89ca05143142
  modified: 2026-07-19T19:47:35.264Z
---

**Model pakietów ustalony 2026-06-30 (z Rafałem). Billing i implementacja = później; tu jest decyzja produktowa.**

**PRIORYTET (Rafał 2026-07-18): egzekwowanie pakietów WSKAKUJE PRZED analitykę** ([[plan-own-analytics]]) — bo przy każdej funkcji (płatności/FV/GA/wysyłki) bramkę odkładaliśmy „na koniec" i narósł mętlik „co jest dla kogo". Kluczowe: MACIERZ JEST JUŻ ZDECYDOWANA (tabela niżej + ustalenia 2026-07-15) — Rafałowi się miesza, bo KOD tego nie egzekwuje, nie dlatego że decyzji brak. Realna robota = (1) bramka `entitlement()` funkcja po funkcji wg macierzy, (2) edytor uprawnień w panelu admina (nadanie pakietu/comped/data — dziś stub, patrz [[plan-admin-panel-and-landing]], ZALEŻNOŚĆ: bez tego nie ma jak nadać dostępu, też Rafałowi na ilikemybike), (3) testy (zero pokrycia logiki pieniężnej). Cennik 0/500/900 zostaje placeholderem (billing = później). PIERWSZY KROK: audyt stanu egzekwowania (read-only) — per pozycja macierzy „bramka JEST/CZĘŚCIOWA/BRAK" → backlog luk. Uwaga: od 2026-07-18 płatności online (Paynow) są zbudowane, ale ŚWIADOMIE bez bramy `online_payments` — to jedna z luk do domknięcia ([[plan-online-payments-mbank]]).

## Monetyzacja
- **SaaS, abonament ROCZNY** (nie prowizja — platforma nie dotyka pieniędzy ze sprzedaży, bo sprzedawca podpina własne integracje płatności, patrz [[plan-shop-settings-storage]]). Rok z premedytacją: jedna większa FV/rok zamiast wielu małych (koszt księgowości), lepszy cash-flow, niższy churn.
- **Brak okresu próbnego** — Free jest de facto trialem (pełny sklep działa wiecznie za darmo). Otwarte, ale na razie bez.
- Kwoty: jeszcze nieustalone (przykładowo padło ~500/rok za tier; 2000/rok za custom 500 produktów).

## Trzy tiery (good–better–best) — ZREWIDOWANE 2026-07-15
Liczby produktów dzielą się na pełne strony siatki (24/48/96), sufit ~100 = platforma „butikowa". **24 świadomie nadpisuje spec §82 („25") — nasze ustalenia > spec, patrz [[decisions-override-spec]].**

**Kram = jedyny darmowy. Stragan i Pawilon płatne.**

| | Kram | Stragan | Pawilon |
|---|---|---|---|
| Produkty | 24 | 48 | 96 |
| Wygląd/kolory | tak | tak | tak |
| **Analityka podstawowa (NASZA)** | tak | tak | tak |
| **Analytics (GA/GTM)** | — | tak | tak |
| Płatności online | — | tak | tak |
| **Wysyłka: InPost + Furgonetka** (pakietem, oba naraz) | — | tak | tak |
| **Faktury (integracja Fakturownia.pl)** | — | tak | tak |
| **Edycja zamówienia** | — | — | tak |
| **Kody rabatowe** | — | — | tak |
| **Korespondencja seryjna** | — | — | tak (planowana) |
| Własna domena | ODŁOŻONA — patrz niżej |

**Zmiany z 2026-07-15 (Rafał):**
- **Własna domena WYPADA z zakresu** — wraca dopiero PO uruchomieniu i pierwszych klientach, gdy Rafał przejdzie na osobny serwer tylko pod Kramio. Do tego czasu nie ma jej w żadnym pakiecie (nie jest to uprawnienie Pawilonu — po prostu nie istnieje).
- **Kody rabatowe: TYLKO Pawilon** (było: Stragan+Pawilon).
- **GA/GTM: od płatnego Straganu** (było: dla wszystkich).
- **Edycja zamówienia: TYLKO Pawilon** (ZMIANA 2026-07-19 — było: oba płatne Stragan+Pawilon). Powód Rafała: Pawilon kosztuje 2× Stragana (150 vs 75/mc, patrz [[pricing-packages]]), więc musi mieć wyraźnie więcej — edycja zamówienia daje mu trzeci konkretny wyróżnik obok kodów rabatowych i korespondencji seryjnej. Domyka [[gate-order-edit-behind-paid]]. Rozważałem obiekcję „to higiena operacyjna, brak zaboli" — Rafał ją obalił i ma rację: (1) brak DOWOLNEJ funkcji da się odczytać jako żal, więc to nie argument przeciw bramkowaniu akurat edycji; (2) w normalnym e-commerce edycji zamówienia NIE MA (standard = anuluj i złóż od nowa) — to nie jest coś, czego sprzedawcy „brakuje", tylko net-new zdolność pracy na już przyjętym zamówieniu, więc legalnie premium. Temat ZAMKNIĘTY, nie wracać.
- **Wygląd/kolory nadal dla WSZYSTKICH** (table-stakes; ładne sklepy = marketing platformy „Powered by Kramio"). NIE jest dźwignią.

**Dalsze ustalenia 2026-07-15 (druga tura):**
- **Faktury = TYLKO integracja z Fakturownia.pl** (API). Rafał wprost: „nie robimy własnego modułu" — my nie jesteśmy wystawcą FV. Pakiety płatne.
- **Wysyłka = InPost + Furgonetka RAZEM, jako pakiet** — Rafał odrzucił moją propozycję rozdzielenia ich między tiery (InPost→Stragan, Furgonetka→Pawilon). Oba naraz w płatnych. Domyka [[shipping-aggregator-idea]]: wybrany broker to **Furgonetka** (obok InPostu bezpośrednio).
- **Korespondencja seryjna → Pawilon** (planowana, do zaprojektowania — patrz [[plan-bulk-mail]]). To ONA wypełnia Pawilon.

**Problem „cienkiego Pawilonu" — ROZWIĄZANY.** Po wypadnięciu domeny Pawilon miał tylko 2 wyróżniki (96 produktów + kody rabatowe), co zgłosiłem jako za mało na osobny tier. Korespondencja seryjna daje mu trzeci i najcięższy gatunkowo. Zostaje do ustalenia cennik (kwoty wciąż placeholderami: 0/500/900).
- **Integracje płatne = wszystkie naraz** (bez granularnego dłubania per integracja). Płatności online (przez operatora) + wysyłka kurierska to główny magnes na upgrade; ich brak we Free jest sam w sobie nudge'em.
- **Free = odbiór osobisty + przelew tradycyjny** (ręczny przelew na konto, bez operatora — zgodne ze spec §488; korekta wcześniejszego „płatność przy odbiorze"). Przelew tradycyjny odblokowuje wysyłkę bez integracji płatności (klient przelewa, sprzedawca wysyła). Płatności ONLINE przez operatora (PayU/P24) = pakiety płatne.
- **Box „dane do przelewu" w ustawieniach sklepu (wymóg, ustalone 2026-06-30):** pole NIEOBOWIĄZKOWE; gdy wypełnione → metoda „przelew tradycyjny" staje się dostępna. Pola: **numer konta** (PL/IBAN — serce, jego obecność włącza metodę), **odbiorca** (domyślnie nazwa firmy z [[plan-nip-autofill-and-editor]], nadpisywalna), **nazwa banku** (opcjonalnie). Tytuł przelewu NIE jest ustawieniem (auto = numer zamówienia). Powód: bez tego sprzedawca wpisuje dane do przelewu brzydko w opisie produktu — to wersja profesjonalna. **DECYZJA (2026-06-30): box budujemy TERAZ w Ustawieniach sklepu**, nie czekamy na checkout — bo numer konta to dana sklepu jak każda inna (ten sam wzorzec co produkty, które istnieją w panelu mimo braku storefrontu). Podpięcie metody „przelew tradycyjny" do checkoutu (widoczność dla kupującego, wybór metody) dojdzie później z modułem zamówień. Wpina się w [[plan-shop-settings-storage]].

## Model przechowywania: SNAPSHOT (materialize) — ROZSTRZYGNIĘTE 2026-07-01
**Rafał wybrał snapshot, nie „różnice". Powód: „kupiłeś — masz to, co kupiłeś".**
- `config/shop.php` = definicje pakietów (co mają + ile kosztują). Źródło prawdy **tylko w momencie zakupu/przypisania**.
- Opłata/przypisanie pakietu → sklep **kopiuje** uprawnienia z configu do siebie (pełny snapshot zapisany na `shops`). Od tej pory żyją własnym życiem.
- Zmiana definicji pakietu w configu PÓŹNIEJ **nie dotyka** już opłaconych sklepów — mają to, co kupili (grandfathering do końca okresu). Dotyczy tylko nowych zakupów.
- **Odrzucony wariant „resolve"** (pakiet=preset + trzymanie tylko odchyleń, `override ?? config`) — bo propagowałby zmiany configu na istniejące sklepy, łamiąc „kupiłeś-masz". To była wcześniejsza (2026-06-30) skłonność, świadomie zmieniona.
- **Odnowienie (po roku) — ROZSTRZYGNIĘTE 2026-07-19, ROZDZIELONE na dwie osie (Rafał). Wcześniejsze mętne „re-snapshot całości" było błędem — kasowałoby ręczne nadania.**
  - **UPRAWNIENIA (co pakiet zawiera): LEPKIE — NIE odnawiają się, NIE re-snapshotują.** Snapshot `entitlements` sklepu przy przedłużeniu zostaje taki, jaki był, i NIGDY nie jest nadpisywany z configu pakietu. Powód: dla dobrego klienta admin ręcznie włącza moduł spoza jego pakietu (np. korespondencję seryjną w Straganie); gdyby odnowienie re-snapshotowało z pakietu, ten dodatek by zniknął. Konsekwencja dla kodu: logika odnowienia MUSI zachowywać istniejący snapshot uprawnień, nie budować go od nowa z `config/shop.php`. Patrz [[plan-per-shop-custom-pricing]].
  - **CENA: osobno — przy odnowieniu domyślnie bierze AKTUALNĄ cenę pakietu** (re-snapshot samej ceny). Cena MA być zapisana jako wartość per sklep (snapshot na `shops`, edytowalna jak każde inne uprawnienie — DZIŚ jej tam nie ma, `price_yearly` żyje tylko w configu → luka do domknięcia). Dokładna polityka (czy/komu zamrażać starą cenę na zawsze) = OTWARTA, wrócimy — na razie: domyślnie aktualny cennik, admin może ręcznie zostawić starą per sklep. Niełatwa decyzja, świadomie odłożona.

Aplikacja NIGDY nie pyta o pakiet wprost — pyta **resolver**: „ile produktów ma ten sklep?", „czy ma płatności online?". Resolver czyta **zapisany snapshot sklepu** (nie config, nie nazwę pakietu). Nazwa pakietu = tylko naklejka informacyjna (admin/FV/komunikacja). Bez `if pakiet == free` w kodzie.
- Zarządzanie per sklep = **edycja zapisanego snapshotu** (to są „nadpisania"). Przykłady, które MUSZĄ działać: znajomy-tester = snapshot Free + ręcznie podbite (96 produktów, płatności on) + comped (nie wygasa); deal 500 produktów/2000 rocznie = dowolny snapshot + ręcznie max_products=500 + data ważności + ręczna FV. **Nie ma „pakietu custom"** — custom = snapshot + ręczne edycje.
- Storage (do decyzji przy wykonaniu): uprawnienia jako **JSON-bag na `shops`** (np. `entitlements` — nowe uprawnienie = nowy klucz w configu, bez migracji; kanoniczna lista kluczy+typów+domyślnych w `config/shop.php`) ALBO osobne kolumny. + `package` (string, etykieta) + `subscription_ends_at` (nullable) + flaga **comped**.
- **Kanoniczna lista uprawnień — ZATWIERDZONA 2026-07-19 (audyt Faza 0), 8 kluczy** (wartości: stall Kram / booth Stragan / pavilion Pawilon):
  - `max_products` (int) — 24 / 48 / 96
  - `online_payments` (bool) — ❌ / ✅ / ✅
  - `courier_shipping` (bool) — ❌ / ✅ / ✅
  - `invoices` (bool) — ❌ / ✅ / ✅  *(nazwa `invoices`, NIE `invoicing` — kod już jej używa)*
  - `ga_analytics` (bool) — ❌ / ✅ / ✅  *(NOWY; GA/GTM od Straganu; NASZA analityka zostaje dla wszystkich, nie jest uprawnieniem)*
  - `order_editing` (bool) — ❌ / ❌ / ✅  *(NOWY; tylko Pawilon)*
  - `discount_codes` (bool) — ❌ / ❌ / ✅  *(tylko Pawilon; funkcja jeszcze nie istnieje)*
  - `bulk_mail` (bool) — ❌ / ❌ / ✅  *(NOWY; korespondencja seryjna, funkcja później)*
  - `custom_domain` — **WYCIĘTY** (domena odłożona). Wygląd/kolory nie są uprawnieniem (dla wszystkich).
- **Comped/ręczny OMIJA wygaśnięcie i auto-zejście** — sklep comped nigdy nie spada.
- Panel admina dostanie **edytor uprawnień per sklep** (snapshot + edycja pól + data ważności + comped) — „konsola" Rafała do ręcznego sterowania. Później.

## Problem zejścia z pakietu (nieopłacenie / downgrade) — rozwiązanie ustalone
- **Miękki zamek, NIE kasowanie.** Przy zejściu: produkty ponad limit → automatycznie `is_active=false` (ukryte), ale ZOSTAJĄ w bazie; po opłacie wracają jednym ruchem. Sprzedawca wskazuje, które zostawić, albo system ukrywa najstarsze.
- Funkcje (domena, płatności online, kody) gasną od razu (domena → wraca subdomena; płatności → znów odbiór osobisty).
- Wpina się w istniejący mechanizm: `is_active` + widoczność sklepu napędzana aktywnymi produktami przez ProductObserver ([[shop-visibility-auto-publish]]) — zero nowej architektury.
- Moment nieopłacenia (przypomnienia „kończy się za 14/7/1 dni", karencja) → wzorzec outbox+cron ([[email-outbox-cron-pattern]]).

## Nazwy i slugi tierów — USTALONE 2026-07-01
Metafora: rosnące miejsce handlowe (w duchu „kramio" = kramik). **Nazwa (PL, widoczna) zmienialna w każdej chwili z configu; slug (EN, w DB/config) stały i ukryty.**

| Nazwa (PL, widoczna) | Slug (EN, `shops.package` / klucz configu) | Rola |
|---|---|---|
| Kram | `stall` | najniższy / darmowy |
| Stragan | `booth` | środkowy |
| Pawilon | `pavilion` | najwyższy |

- Rafał świadomie wybrał slugi tematyczne (nie neutralne `starter/standard/premium`) — dbałość o detal marki, w pełni OK; `stall`/`booth`/`pavilion` są rozróżnialne.
- Slug NIE koduje kolejności → w `config/shop.php` dodać jawny porządek tierów (pole `order` lub kolejność w tablicy), by kod wiedział co jest awansem/zejściem (upgrade/downgrade, miękki zamek).
- „Sklep" jako nazwa najwyższego ODRZUCONE (generyczne + kolizja z zakładką „Mój sklep"). „Free" jako nazwa też porzucone — darmowy tier to „Kram".

## Stan w kodzie (Faza 1 wdrożona 2026-07-19)
**Fundament + Faza 1 GOTOWE:**
- `config/shop.php` → `packages` (stall/booth/pavilion). **Ceny 0 / 750 / 1500 BRUTTO** (rok=10×; było placeholder 0/500/900). **8 kluczy uprawnień** wg zatwierdzonej macierzy; `custom_domain` WYCIĘTY; dodane `ga_analytics`/`order_editing`/`bulk_mail`; `invoices` i `discount_codes` poprawione na właściwe tiery.
- Migracja `2026_07_01_140000...` → `package`, `entitlements` (JSON), `subscription_ends_at`, `comped`. **NOWA migracja `2026_07_19_120000_add_price_yearly_to_shops_table`** → `price_yearly` (decimal, snapshot per sklep).
- `Shop::assignPackage()` snapshotuje TERAZ też cenę; `Shop::entitlement()` (snapshot→fallback), **`Shop::priceYearly()`** (snapshot→fallback), `Shop::packageName()`. `ShopFactory` ma `price_yearly` + state `withInvoicing()`.
- 3 testowe Kramy zsynchronizowane do 8-kluczowego snapshotu (assignPackage).
- **Testy: `Seller/PackageEntitlementsTest` ISTNIAŁ (mój audyt błędnie mówił „zero testów")** — rozszerzony o macierz 3 tierów (dataProvider), drabinkę cen, resolver `priceYearly`, wycięcie `custom_domain`. Cały suite **719 zielonych**.

**Faza 2 GOTOWA (2026-07-19, commit 0574856) — edytor uprawnień w panelu admina (linchpin):**
- Konsola sklepów: lista `/administrator/sklepy` (`Administrator\ShopController`) + edytor (Livewire `Administrator\ShopManager`). Admin ustawia per sklep: pakiet (preset), każdą flagę uprawnień osobno, `max_products`, cenę roczną, `subscription_ends_at`, `comped`.
- Zapis pisze WPROST snapshot (nie woła assignPackage) → ręczne nadpisania nie giną. „Nadaj pakiet" tylko wypełnia formularz. Oba widoki mają prawą kolumnę (opisy), jak panel sprzedawcy. Testy: `Administrator\ShopListTest` + `ShopManagerTest`.
- **Można już RĘCZNIE sprzedawać pakiety** (nadać komuś Stragan/Pawilon/deal). Billing automatyczny wciąż odłożony — pieniądze z ręki.

**Faza 3 KOMPLETNA (2026-07-19, commity fdfea44/a8d9c6e/7766c9b + ga_analytics) — bramki egzekwujące:**
Wzorzec każdej bramki: centralny „efektywny stan" w modelu Shop (uprawnienie + dotychczasowe warunki) + zapis konfiguracji w kontrolerze tylko gdy uprawnienie + `@if(entitlement)` w widokach + `ShopFactory::with*()` + testy dowodowe „skonfigurowany bez uprawnienia = brak funkcji".
- `max_products` — `ProductController` (od początku).
- `invoices` — bramka była, zaczęła egzekwować po naprawie configu (Faza 1).
- `online_payments` — `Shop::onlinePaymentsEnabled()`; karta Paynow (Integracje) + przełącznik (Ustawienia).
- `courier_shipping` — `Shop::courierAvailable()` + `parcelLockerAvailable()` (kurier+paczkomat razem; Kram = tylko odbiór osobisty); bloki dostaw w Ustawieniach.
- `order_editing` — `OrderEditor::editable()` + guard w `run()` (Pawilon only).
- `ga_analytics` — `Shop::tracksWithGoogleAnalytics()`; karta GA (Integracje) + przełącznik (Ustawienia).
- BONUS: pusta strona Integracji dla Kramu → stan pusty/upsell „Integracje w wyższych pakietach"; sekcja Integracje w Ustawieniach też ma upsell.

**Czego wciąż NIE ma (Faza 4+):**
- Miękki zamek przy zejściu, obsługa wygaśnięcia (`subscription_ends_at`/`comped` czytane nigdzie), przypomnienia, UI wyboru/porównania pakietów + publiczny Cennik na głównej, billing (Faza 4–5).
- Funkcje `discount_codes` i `bulk_mail` jako ficzery **nie istnieją** (tylko flagi w configu — nie ma czego bramkować).
- Pulpit admina: realne dane (Sklepy + lista najnowszych) ZROBIONE. Kafelki SPRZEDAŻY SaaS (sprzedane abonamenty, przychód sumarycznie/12mc) to świadome placeholdery `0` — decyzja Rafała „nie robić łatek": docelowe kafelki teraz, dane przy billingu. PUNKT PODMIANY: 3 pola w `Administrator\DashboardController` (`subscriptionsSold`/`saasRevenueTotal`/`saasRevenue12m`). Wymaga rejestru sprzedaży/odnowień (Faza 4). Lista sklepów ma filtry (szukajka+pakiet+status).
- **Uwaga infra:** build assetów (Rolldown) bywa za wolny/timeoutuje — nowe widoki admina używają obejść (inline `style`, dobór klas obecnych w buildzie). Warto dobić porządny rebuild osobno; patrz [[vite-build-rayon-threads]], [[tailwind-classes-must-exist-in-build]].

Powiązane: [[plan-shop-settings-storage]], [[multitenant-subdomain-architecture]], [[shop-visibility-auto-publish]], [[email-outbox-cron-pattern]], [[storefront-theme-system]].
