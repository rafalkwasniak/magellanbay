---
name: plan-own-analytics
description: "Własna analityka: Poziom 1 i 2 WDROŻONE, od 30.08 ten sam ekran ma też admin w przekroju platformy (ShopAnalytics przyjmuje ?Shop). Poniżej szkic z 18.07 jako kontekst — rollup dzienny→miesięczny wciąż otwarty."
metadata: 
  node_type: memory
  type: project
  originSessionId: 61bf4af1-b55f-4f33-879d-3180420732e7
  modified: 2026-08-30T20:21:50.341Z
---

## STAN 2026-08-30 (WDROŻONE, commit `6b54942`)

Poziom 1 i Poziom 2 (ruch z `shop_stats`) **działają** w dziale „Analityka" sprzedawcy. **Od 30.08 ten sam ekran ma administrator** pod `/administrator/analityka`.

- **Jeden serwis, dwa zakresy:** `App\Services\ShopAnalytics::for(?Shop $shop, …)` — `null` = cała platforma. Zakres decyduje się WYŁĄCZNIE w `ordersQuery()`; nie dopisywać drugiego serwisu ani `where('shop_id')` gdzie indziej, bo wtedy dwa panele zaczną podawać dwie prawdy o tych samych zamówieniach.
- **Wspólny widok:** główna kolumna (KPI, ruch, wykres, bestsellery, podziały, klienci) to komponent `<x-analytics.dashboard>`. Zmiana wyglądu analityki dotyka OBU paneli naraz — to celowe.
- **Klient = sklep + e-mail** (`customerKey()`). Decyzja Rafała 30.08: ta sama osoba kupująca w dwóch sklepach to DWÓCH klientów, żeby suma platformy zgadzała się z sumą pojedynczych sklepów. „Powracający" = wrócił do tego samego sklepu.
- **Do sumy wchodzą wszystkie sklepy z bazy**, także wyłączone i w karencji na usunięcie (sprzedaż się wydarzyła). Inaczej niż `PackageRevenue`, który sklepy w karencji pomija — tam chodzi o pieniądze do zainkasowania, nie o historię.
- **Filtry admina:** formularz GET `sklep` + `okres` (wzorzec z działu „Zamówienia"). Nieznane `?sklep=` cicho wraca do sumy.
- **Znane ograniczenie:** przy zakresie platformowym zamówienia z okna nadal lecą do PHP. Pierwsze miejsce do przepisania na SQL, gdy sklepów z ruchem będzie kilkaset.

---

**Poniżej szkic z 2026-07-18 — kontekst decyzji, nie stan.** Nadal otwarte: granularność rollupu (trzymaj dziennie, pokazuj miesięcznie).

**Szkic koncepcji, NIE plan do wykonania. Rozmowa otwarta.** KOLEJNOŚĆ (Rafał 2026-07-18): NAJPIERW egzekwowanie pakietów ([[plan-packages]]), analityka PO nim — żeby dział Analityka urodził się od razu z właściwą bramką, nie jako kolejny luźny koniec. Kierunek zgodny z [[dashboard-stats-direction]]: własna podstawowa analityka dla WSZYSTKICH sklepów; GA/GTM = płatny dodatek (Stragan+, [[plan-packages]]). Cel: „fajne dane" bez obciążania shared hosta ([[shared-hosting-constraints]], limit procesów [[open-hosting-process-limit]]).

**Zasada naczelna:** większość wartości liczy się Z DANYCH, KTÓRE JUŻ MAMY (orders/order_items/products/customers) — zero zapisów przy wejściu na stronę. Obciąża dopiero śledzenie ruchu, i to robimy licznikami-agregatami, nie wierszem-na-kliknięcie.

**POZIOM 1 — z istniejących danych (zero kosztu przy żądaniu):**
Sprzedaż w czasie (obrót, liczba zamówień, AOV); bestsellery/leżaki; lejek złożone→opłacone→zrealizowane + % anulowań; podział metod płatności/dostawy; nowi vs powracający klienci, % ponownych zakupów, top klienci; wzorce pora dnia/dzień tygodnia; niski stan. Część JUŻ JEST (statystyki pulpitu/zamówień z [[handoff-2026-07-10]]) → START = AUDYT co mamy, ile Fazy 1 zostaje do dobudowania. Przy tej skali rollup NIEPOTRZEBNY — sumy liczone w locie z orders + cache.

**POZIOM 2 — lekki ruch/konwersja (to dopiero dokłada obciążenie):**
Rzeczy spoza bazy: wizyty, wyświetlenia produktu, % dodań do koszyka, konwersja wejścia→zamówienie. NIE wiersz-na-kliknięcie (zabija shared host + puchnie bez końca), tylko INKREMENT liczników w tabeli rollup (np. `shop_stats`: shop_id, okres, wizyty, wyświetlenia_produktów, dodania_do_koszyka, zamówienia…). Zbieranie: tani upsert +1 albo bufor w cache flushowany cronem; filtr botów po user-agent. Bez trzeciego JS-taga (to działka GA) — pierwsza strona, agregat. RODO-friendly (agregaty, brak profilowania) = argument sprzedażowy.

**OTWARTY PUNKT — granularność rollupu (Rafał 2026-07-18):**
Rafał rozważa 1 wiersz/sklep/MIESIĄC (darmowe, porównanie miesiąc-do-miesiąca; dla małych sklepów z kilkoma wejściami/dzień dzienny słupek = szum ~0, a dzień-do-dnia to i tak GA). Zgoda co do PREZENTACJI (domyślny widok miesięczny). Mój niuans co do SKŁADOWANIA: dzienny wiersz też ~darmowy (365/sklep/rok = nic), a z dziennych zawsze złożysz miesięczne — odwrotnie nie; „tylko miesięcznie" oszczędza ~zero, zamyka „dzień tygodnia" na zawsze, a bieżący niepełny miesiąc wygląda sztucznie nisko. Rekomendacja: TRZYMAJ dziennie, POKAZUJ miesięcznie — ale „tylko miesięcznie" to obronny wybór na prostotę. DO DECYZJI.

**Zasady „nie obciążać" (pod nasz hosting):** zero ciężkiej pracy synchronicznie w żądaniu (tani +1 albo cron); rollup + cache dashboardu (krótki TTL); indeksy (shop_id, okres); cron wzorcem który mamy (withoutOverlapping, queue --stop-when-empty, [[email-outbox-cron-pattern]]). UNIKAĆ: tabeli wiersz-na-wyświetlenie, dashboardów liczących na żywo z surowych zdarzeń, ciężkiego bundla JS.

**NOWY DZIAŁ W PANELU (Rafał 2026-07-18): „Analityka" (📊).** Osobna pozycja w menu sprzedawcy (obok Pulpit/Zamówienia) — NIE na Pulpicie (Pulpit = szybki rzut operacyjny; Analityka = pełny dashboard, efekt WOW). Dostępna dla wszystkich (własna analityka = baza), płatne rozszerzenia (dłuższa historia/eksport) ewentualnie później [[plan-packages]]. Układ od góry: (1) selektor okresu (domyślnie miesiąc) + kafelki KPI (duża liczba: Obrót/Zamówienia/AOV/Konwersja, Δ% vs poprzedni okres, sparkline); (2) sprzedaż w czasie (słupki miesiąc-do-miesiąca); (3) bestsellery (poziome paski) + podział płatności/dostawy (donut/stacked); (4) nowi vs powracający + top klienci. Ciepła paleta panelu (amber/rose), emoji tylko w ikonach ([[ui-design-direction]]).

**WYKRESY — jak, żeby nie obciążyć (Rafał chce wykresów, efekt WOW):** obciąża LICZENIE danych, nie RYSOWANIE — wykres dostaje garść gotowych liczb (12 miesięcy, top5), render tani niezależnie od techniki. REKOMENDACJA: wykresy renderowane SERWEROWO jako SVG/CSS (słupki, sparkline, donut) — zero ciężkiego bundla JS, działają bez JS, kolory z motywu, ładne od razu, idealne pod shared host i pod „bez trzeciego taga/bloatu". Mały JS tylko punktowo (interaktywne tooltipy na wykresie liniowym) — lekka lib, nie Chart.js-moloch. Dane zawsze z policzonych agregatów + cache, więc wejście na Analitykę nie odpala ciężkich zapytań. Decyzja SVG-serwerowo vs mała-lib = na później; głos: ZACZĄĆ od SVG-serwerowo. Uwaga na build: nowe klasy Tailwind muszą być w buildzie [[tailwind-classes-must-exist-in-build]]; przy realizacji jest skill `dataviz`.

**Fazowo (rekomendacja):** F1 = dashboard „Analityka" z istniejących danych (Poziom 1) — ogromna wartość, zero kosztu śledzenia, TU ZACZĄĆ (od audytu istniejących statystyk). F2 = lekkie liczniki ruchu/konwersji (Poziom 2, rollup). GA/GTM = płatny wariant behawioralny.

Powiązane: [[dashboard-stats-direction]], [[plan-packages]], [[shared-hosting-constraints]], [[open-hosting-process-limit]], [[email-outbox-cron-pattern]], [[handoff-2026-07-10]].
