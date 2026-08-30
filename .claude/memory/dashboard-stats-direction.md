---
name: dashboard-stats-direction
description: "ZMIENIONE 2026-07-15 — budujemy WŁASNĄ podstawową analitykę (dla wszystkich, w tym Kramu); GA/GTM przechodzi do płatnego Straganu. Odwraca decyzję z 2026-07-10."
metadata: 
  node_type: memory
  type: project
  originSessionId: d43b718a-bf6f-4d34-bb7d-d0b76a72a4c6
---

**DECYZJA ODWRÓCONA 2026-07-15.** Wcześniej (2026-07-10) ustalono: „analitykę ruchu robi GA, nie liczymy wyświetleń serwerowo; nie wracać do pomysłu `shop_daily_views` + middleware". **To już NIE obowiązuje.**

## Co obowiązuje teraz
- **Budujemy własną, podstawową analitykę** — dostępna dla WSZYSTKICH pakietów, w tym darmowego Kramu, w standardzie sklepu.
- **GA/GTM przechodzi do płatnego Straganu** (patrz [[plan-packages]]) — jako opcja dla tych, którzy chcą więcej.
- Kafelek „Wyświetlenia (30 dni)" był usunięty z Pulpitu przy poprzedniej decyzji — **wraca**, tym razem z realnymi danymi.

## Dlaczego zmiana (uzasadnienie Rafała, 2026-07-15)
Nowa informacja z rynku, której nie było 10.07: **rozmowa z projektantem i wdrożeniowcem — nie każdy chce zakładać konto GA i przedzierać się przez jego zasoby.** Własna analityka nie jest substytutem GA, tylko prostą rzeczą dla ludzi, którzy GA nie chcą tknąć.

Druga noga: poprzednia decyzja zakładała, że GA ma KAŻDY. Po przeniesieniu GA do Straganu darmowy Kram zostawałby ze ślepym pulpitem — a wtedy argument „własny licznik to duplikat GA" przestaje obowiązywać, bo nie ma czego duplikować.

## Co z pierwotnym argumentem przeciw (WCIĄŻ WAŻNY)
Odrzucono to wtedy m.in. z powodu **zapisów per request na shared-hostingu** ([[shared-hosting-constraints]]) — i ten argument nie zniknął, zwłaszcza że konto ma dziś realny problem z limitem procesów ([[open-hosting-process-limit]]). Przy budowie: NIE zapis-per-odsłona wierszami. Kierunek do rozważenia — dzienny agregat z atomowym upsertem (`INSERT … ON DUPLICATE KEY UPDATE`), jedno lekkie zapytanie na odsłonę, bez rosnącej tabeli zdarzeń. Ruch sklepów butikowych jest mały, więc to wystarczy.

**OTWARTE — zakres nieustalony.** Do rozstrzygnięcia przed budową: co dokładnie pokazujemy (odsłony sklepu? odsłony per produkt / topka? źródła ruchu przez referrer? konwersja odsłony→zamówienia?), za jaki okres, czy botów nie liczymy, i czy dane trzymamy bezterminowo czy z retencją.

**How to apply:** przy prośbie o „statystyki na Pulpicie" — już NIE odsyłać do GA. Budować własne, ale lekko (agregat, nie log zdarzeń). Kafelkowy rząd „Twoja sprzedaż" nadal wypełniać metrykami z naszej bazy (zamówienia, przychód, w przyszłości wysyłki). Powiązane: [[plan-packages]], [[handoff-2026-07-03]] (GA/GTM już zintegrowane per sklep).
