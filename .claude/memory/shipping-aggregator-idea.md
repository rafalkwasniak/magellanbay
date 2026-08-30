---
name: shipping-aggregator-idea
description: Do analizy przy module dostaw — integracja z brokerem przewozów (Furgonetka/Apaczka/Sendit) zamiast osobno z każdym kurierem; jedna integracja = wielu dostawców.
metadata: 
  node_type: memory
  type: project
  originSessionId: 5012e4a9-f72a-42fb-a86c-3d1f251b9888
  modified: 2026-08-08T15:54:41.531Z
---

**ZAMKNIĘTE 2026-08-08 — BROKERA NIE ROBIMY.** Sonda API udowodniła, że **InPost nadaje kurierem pod adres na koncie, które sprzedawca już ma** ([[plan-inpost-courier]]). Decyzja Rafała: „jeśli to wyjdzie, zapominamy o Furgonetce i kolejnych umowach sprzedawcy". Broker przestał rozwiązywać jakikolwiek problem — wracać TYLKO, gdyby ścieżka InPostu odpadła.

**Analiza brokerów z 08.08 (zachowana na taką okoliczność):** kryterium rozstrzygającym nie jest cennik ani liczba kurierów (wszyscy mają DPD/DHL/GLS/UPS/InPost/Pocztę), tylko **model integracji dla platformy**. Furgonetka — OAuth2, my rejestrujemy aplikację, sprzedawca klika „Połącz"; sandbox `api.sandbox.furgonetka.pl` istnieje, ale droga wejścia nieudokumentowana. Apaczka — **odpada: BRAK sandboxa** (dokumentacja podaje tylko produkcję) + wymaga podpisanej umowy i telefonu do BOK, by włączyć API; HMAC-SHA256 per żądanie. Sendit — dokumentacja niepubliczna. Sendcloud — technicznie najlepszy (publiczny `sendcloud.dev`, program partnerski), ale **własny abonament sprzedawcy** obok Kramio, wsparcie po angielsku. Gdyby wracać: Furgonetka, alternatywa Sendcloud, NIE Apaczka.

---

**Historyczne: ROZSTRZYGNIĘTE 2026-07-15 — broker = FURGONETKA**, obok InPostu integrowanego bezpośrednio. Oba w pakietach płatnych, razem (Rafał odrzucił rozdzielanie ich między tiery). Model wdrożenia i stan → [[plan-shipping]], podział na pakiety → [[plan-packages]]. Poniżej kontekst pierwotny.

---

**Pomysł Rafała (2026-07-03), do analizy PÓŹNIEJ — nie robimy teraz.** Zamiast integrować się osobno z każdym przewoźnikiem (DPD, DHL, GLS, InPost…), rozważyć integrację z **brokerem/agregatorem przesyłek**, który sam dobiera i obsługuje wielu dostawców. Jedna integracja → wiele kurierów, często taniej (cenniki brokera), generowanie etykiet, śledzenie, punkty odbioru/paczkomaty w jednym API.

Polscy gracze do sprawdzenia: **Furgonetka**, **Apaczka**, **Sendit**, **BaseLinker** (szerszy: zamówienia+wysyłki+integracje), ewentualnie natywne API InPost/DPD, gdyby broker nie wystarczał.

Kontekst: pasuje do modułu dostaw ([[plan-shop-settings-storage]] kat.3 wspomina inpost/kuriera; [[plan-shop-edit-tabs]]). Metody dostawy są per-sklep (nazwa/opis/koszt/aktywna — spec „Dostawy"); broker byłby integracją (kat. 3, wyższe pakiety [[plan-packages]]), która wystawia kilka metod dostawy naraz. Na MVP mamy tylko odbiór osobisty. Adres dostawy vs adres firmy trzymamy rozdzielnie ([[stock-availability-verification]] sąsiaduje z checkoutem) — przy paczkomacie dostawa = wybrana skrzynka, bez adresu.
