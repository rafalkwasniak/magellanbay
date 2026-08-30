---
name: legal-consumer-returns-withdrawal
description: "Zwroty/odstąpienie 14 dni — MODUŁ KOMPLETNY w zakresie v1 (Fazy A+B+C, 2026-07-29): flaga art. 38, formularz tokenowy, pomniejszanie zamówienia z ilości efektywnej, maile, panel. Poza v1 tylko automat pieniędzy i FV korygująca."
metadata: 
  node_type: memory
  type: project
  originSessionId: 2e8fa019-7e9e-4cb3-ab6b-a93b0b13411b
  modified: 2026-07-29T16:12:13.054Z
---

POMYSŁ/WYMÓG 2026-07-20 (Rafał pytał; NIE do wdrożenia teraz, ale wziąć pod uwagę w pracach).

**Podstawa:** Dyrektywa UE 2011/83/UE o prawach konsumentów → PL: **Ustawa z 30 maja 2014 o prawach konsumenta**. Stabilne prawo (od 2014; od 1.01.2021 też „przedsiębiorca na prawach konsumenta").

**Sedno — prawo odstąpienia (zwroty):** konsument kupujący na odległość może odstąpić od umowy w **14 dni bez podania przyczyny**. Liczone od DORĘCZENIA towaru. Brak pouczenia → termin rośnie do **12 miesięcy**. Klient składa oświadczenie (jest ustawowy WZÓR formularza, sklep musi go udostępnić; klient nie musi go użyć). Zwrot towaru w 14 dni; sklep zwraca kasę w 14 dni (w tym najtańszą oferowaną dostawę), może wstrzymać do otrzymania towaru/dowodu nadania. Koszt odesłania zwykle po stronie klienta (jeśli poinformowany).

**WAŻNE dla Kramio — wyjątki art. 38** (prawo NIE przysługuje): towary nietrwałe/szybko psujące się (**kwiaty, żywność**), na indywidualne zamówienie/**personalizowane (rękodzieło)**, zapieczętowane ze względów higienicznych. Duża część naszych sprzedawców! → platforma powinna pozwalać **oznaczyć wyłączenie**, nie wymuszać procedury tam, gdzie z prawa nie obowiązuje.

**Nie mylić:** odstąpienie (14 dni, zmiana zdania) ≠ **rękojmia/reklamacja** (wada towaru, do 2 lat) — osobny obowiązek, też do ogarnięcia w obsłudze posprzedażowej. Przy wdrożeniu dopiąć dokładne cytaty artykułów pod treść pouczenia.

---

## USTALONY PLAN MODUŁU (2026-07-20, Rafał doprecyzował) — W KOLEJCE, NIE robimy teraz

Rafał: „nic nie robimy, zapisz i będzie czekało w kolejce, mamy ważniejsze rzeczy". Plan zatwierdzony, START = po jego słowie. **Dostępne we WSZYSTKICH pakietach — bez bramki (wymóg prawny), „niestety" (nie da się gate'ować).**

**Cztery filary:**
1. **Pouczenie w mailu o zamówieniu** — warunkowo, tylko jeśli zamówienie ma pozycje objęte prawem zwrotu.
2. **Opt-out per produkt** — flaga `withdrawal_excluded` (domyślnie `false` = objęty; sprzedawca WYŁĄCZA dla art. 38: kwiaty/żywność/personalizowane). Powód: klient w jednym zamówieniu może mieć stroik z żywych kwiatów (wyłączony) + 3 donice (objęte).
3. **Formularz online na tokenie (bez logowania)** — link w mailu I w panelu klienta (szczegół zamówienia) → ten SAM link („zawsze w jednym miejscu"). Strona: pokazuje zamówienie, pozycje objęte wybieralne (ilość ≤ pozostała), wyłączone WYSZARZONE; klient wybiera co i ile zwraca, podaje dane wymagane prawem; z kwotami (za ile kupione / ile do zwrotu). Wysyła → sklep dostaje mail + oznaczenie przy zamówieniu.
4. **Pomniejszanie zamówienia + widoczność** — patrz niżej.

**MECHANIKA pomniejszania (ważne — „nie w nieskończoność 3 doniczki"):** `order_items` dostaje akumulator `returned_quantity`. Zwracalne = `quantity − returned_quantity`. Sumy zamówienia przeliczają się z ilości EFEKTYWNYCH, ale oryginał + log zwrotów ZOSTAJĄ → mniejsze zamówienie z historią; kolejny zwrot widzi tylko resztę. **STANU MAGAZYNOWEGO NIE RUSZAĆ** (to NIE anulowanie — produktu fizycznie nie ma, może być niezdatny do sprzedaży). Widoczność zwrotów w panelu sprzedawcy I klienta (co/ile/za ile/kiedy).

**TERMIN zgłoszenia (kompromis, bo nie znamy daty odbioru):** 14 dni + 4 dni dostawy liczone od zmiany statusu na **`Completed`** (label „Zrealizowane"), a jeśli brak takiego eventu → od `orders.created_at` (klient ma prawo odstąpić też przed odbiorem).

**ZAKRES v1 (Rafał wybrał „rejestracja + ręczne pieniądze"):** v1 rejestruje zwrot, pomniejsza zamówienie, powiadamia. **Zwrot płatności (Paynow refund) i faktura korygująca (Fakturownia) = RĘCZNIE przez sprzedawcę.** Automat pieniędzy/korekty = OSOBNA, późniejsza faza (nie betonować, ale nie w v1).

**FAZY (przystanek po każdej):**
- **Faza A — WDROŻONA 2026-07-25 (844 testy).** `products.withdrawal_excluded` (migracja + fillable/cast + `Product::isWithdrawable()` + checkbox w karcie „Cena i dostępność" + reguła w ProductRequest + domyślne `false` w ProductFactory); `Order::hasWithdrawableItems()` / `withdrawalDeadline()` / `withinWithdrawalWindow()`; terminy w `config/legal.php` → `withdrawal.days` (14) + `withdrawal.delivery_buffer_days` (4); `OrderMailer::withdrawalBlock()` w mailu potwierdzającym (pouczenie + wzór oświadczenia + wymienione z nazwy pozycje wyłączone). Zamówienie WYŁĄCZNIE z towarów art. 38 nie dostaje pouczenia (informowanie o nieistniejącym prawie wprowadza w błąd). Pozycja bez produktu (skasowany) liczy się jako OBJĘTA — przy niepewności na korzyść konsumenta. Testy: `tests/Feature/Legal/WithdrawalNoticeTest.php`. **Obowiązek informacyjny SPEŁNIONY** (brak pouczenia = termin 12 mies. zamiast 14 dni).
- **Fazy B i C — WDROŻONE 2026-07-29, commit `e2ec2a5` (1011 testów).** MODUŁ KOMPLETNY w zakresie v1. Szczegóły poniżej.
- **Poza v1 (nie robione świadomie):** automat zwrotu pieniędzy (Paynow refund) i faktura korygująca — sprzedawca robi ręcznie.

## Co dokładnie stoi po Fazach B/C (2026-07-29)

**Sedno architektoniczne — jedna formuła zamiast trybu zwrotów:** `OrderTotals::recalculate()` liczy z ilości EFEKTYWNEJ (`quantity − returned_quantity`), więc zwrot pomniejsza zamówienie tą samą drogą co edycja pozycji w panelu. `quantity` zostaje nietknięte jako migawka zakupu i jako sufit magazynowy w `OrderEditor`.

**Za tą zmianą MUSIELI pójść wszyscy konsumenci ilości** (to była najbardziej pracochłonna, niewidoczna część): `FakturowniaService` (pozycje zwrócone w całości wypadają z FV, częściowe idą w ilości efektywnej — inaczej faktura pokazywałaby 3 szt. za cenę 2), `OrderMailer::productLines()`, `ShopAnalytics` (zwrócone sztuki to nie sprzedaż; trzeba było dodać `returned_quantity` do `get([...])`).

**Klucz:** `OrderItem::effectiveQuantity()` (do kwot) vs `returnableQuantity()` (do formularza — zeruje się przy art. 38). To NIE są synonimy, choć zwykle dają tę samą liczbę.

**Stan magazynowy nietknięty** + guardy w `OrderEditor`: nie da się zejść z ilością poniżej zwróconej ani usunąć pozycji ze zgłoszonym zwrotem.

**Zgłoszenie = fakt, nie wniosek** (decyzja Rafała): brak statusów accept/reject, jedyna kolumna decyzyjna to `refunded_at`. Odstąpienie działa z mocy prawa, sprzedawca go nie zatwierdza.

**Dostawa nie jest zerowana** (decyzja Rafała): przy zwrocie całości sprzedawca dostaje PODPOWIEDŹ o oddaniu kosztu dostawy, bo ustawa każe oddać najtańszą OFEROWANĄ, a klient mógł wybrać droższą.

**Prawo, o którym łatwo zapomnieć:** art. 30 ust. 2 — potwierdzenie otrzymania oświadczenia złożonego elektronicznie jest OBOWIĄZKOWE (`OrderMailer::returnAcknowledged()`). Art. 32 ust. 1 — 14 dni na zwrot pieniędzy liczone od OTRZYMANIA oświadczenia (`OrderReturn::refundDeadline()`), wstrzymanie do czasu otrzymania towaru zawiesza wykonanie, ale NIE przesuwa terminu.

**ODSTĘPSTWO OD PLANU:** zwrot NIE trafia na oś czasu zamówienia. `order_status_events` to tabela przejść statusu tworzona wyłącznie przez `changeStatus()`; zwrot statusu nie zmienia, więc wpis wymagałby fałszywego przejścia „z X na X" i rozbiłby jedyne źródło prawdy o historii. Zwroty mają własną kartę z datami — u sprzedawcy i u klienta.

**Pliki:** `OrderReturnService` (blokada wiersza, kwoty od ceny PO rabacie tym samym `DiscountAllocation::spread`), `OrderReturnController` + `OrderReturnRequest`, `storefront/order-return.blade.php` (układ 2-kolumnowy jak potwierdzenie zamówienia — `max-w-md` z rejestracji było za wąskie, Rafał: „wygląda jak zakładka do książki"), `Livewire\Seller\OrderReturns`. Testy: `Legal/OrderReturn{Registration,Form,Notification}Test`, `Seller/OrderReturnsPanelTest`.

**Przykład na produkcji (zostawiony celowo):** zamówienie #35 w `ilikemybike` ma realny zwrot 342,92 zł (Shima OpenAir Brown, cała pozycja), termin do 12.08.2026.

## Termin i zamknięcie formularza (doprecyzowane 2026-07-29, commit `e317d40`)

`delivery_buffer_days` podniesione 4 → **6 dni kalendarzowych** (założenie Rafała: sprzedawca nadaje w ciągu 4 dni roboczych = 6 kalendarzowych z weekendem). Świadomie BEZ kalendarza dni roboczych — precyzja pozorna, skoro daty doręczenia i tak nie znamy. Łączne okno: **20 dni** od `Completed` albo od złożenia zamówienia.

**Zasada, o którą warto dbać przy każdej zmianie tekstów:** strona po terminie NIE twierdzi, że prawo wygasło („Formularz zwrotu jest już zamknięty", data podana jako OSZACOWANIE, informacja że prawo liczy się od doręczenia, skierowanie do sprzedawcy z adresem kontaktowym). Powód: bez eventu `Completed` liczymy od złożenia zamówienia i możemy zamknąć formularz ZANIM prawo klienta wygaśnie — komunikat „termin minął" byłby wtedy nieprawdą, którą sami wyświetlamy, a brak rzetelnego pouczenia wydłuża termin do 12 miesięcy. Automat zostaje zamknięty (spóźniony link nie zmieni zamówienia), ale decyzja wraca do sprzedawcy, który jako jedyny wie, kiedy realnie wysłał paczkę.

**Strona główna:** obsługa zwrotów wymieniona w każdym z trzech pakietów (także darmowym) + zdanie pod nagłówkiem sekcji Pakiety.

**Wzorce w kodzie (gotowe do powtórki):** link tokenowy = `Order::paymentToken()` (token = szyfrowane `id`, zero kolumn); pouczenie w mail = `OrderMailer::statusChanged()` składa maile blokami; oś czasu = `order_status_events` (data `Completed`); flaga produktu = obok istniejących w `seller/products/form.blade.php`. Powiązanie FV z zamówieniem = kolumny `invoice_*` na `orders` (brak pola korekty — dobudować przy automacie). Uwaga: `Product::hasBeenOrdered()` to dziś zaślepka `false` (brak relacji `orderItems()`).
