---
name: plan-ai-chunked-correction
description: "PLAN (ustalone 2026-07-26, do zrobienia): korekta AI dzielona na fragmenty — jeden mechanizm w ai.js dla WSZYSTKICH pól. Usuwa timeout przy długich tekstach."
metadata: 
  node_type: memory
  type: project
  originSessionId: 3555802d-d4fb-4f61-936d-2ec6d0355b27
  modified: 2026-07-26T21:00:01.920Z
---

**STAN KOŃCOWY 2026-07-26: build `app-BJWg1XXY.js` (22:57), 855 testów. Wersja druga, po zgłoszeniu Rafała, że regulamin wisi.**

**NAJWAŻNIEJSZA LEKCJA — pierwsza wersja dzieliła TYLKO po blokach najwyższego poziomu i to było ZA MAŁO.** Prawdziwy regulamin wklejony do Trixa to **jeden `<div>` na 25 893 znaki z 200 `<br>` w środku** (strona 1 = „Regulamin", 26 145 zn., 2 bloki: 252 + 25 893). Fragment „2 z 2" wysyłał więc prawie 26 tys. znaków jednym wywołaniem — dokładnie to, czemu dzielenie miało zapobiec. Mój test dzielenia był na SYNTETYCZNYM regulaminie z ładnymi `<h2>/<ul>` i dowodził poprawności czegoś, co w praktyce nie występuje. **Testuj na treści z bazy, nie na wymyślonej.**

Poprawki w wersji drugiej:
- `splitBlock()` — blok większy niż limit tnięty WEWNĄTRZ, po `<br>`; każda paczka dostaje z powrotem oryginalne opakowanie.
- `reassemble()` + `unwrap()` — kawałki jednego bloku wracają do JEDNEGO bloku (inaczej regulamin wróciłby jako 22 osobne akapity = cicha przebudowa struktury użytkownika). Zweryfikowane: 26 145 zn. → 26 fragmentów, tekst po złożeniu identyczny, bloki 2/2.
- **Fragmenty lecą RÓWNOLEGLE po 3** (`CONCURRENCY`), nie sekwencyjnie. Pomiar na opisie produktu: całość jednym wywołaniem 19,1 s vs 2 fragmenty równolegle 10,3 s.
- `chunk_chars` 2500 → **1200** (przy 2500 typowy opis produktu szedł jednym kawałkiem i nic nie zyskiwał).
- Licznik sekund pokazywany ZAWSZE, także przy fragmentach („3 z 12 · 24 s") — Rafał zgłosił, że „2 z 2" stało bez żadnego znaku życia.

Poprzednia wersja (build `app-B9D2oy3h.js`), zachowana jako zapis: Dzielenie zweryfikowane: 31 realnych treści z bazy bez utraty tekstu; syntetyczny regulamin 11 952 zn. → 6 fragmentów ≤2500, zero rozerwanych znaczników. Wykonane wg planu poniżej, w tym: throttle 30→120/min (fragmenty), limit wyniku per fragment w `AiController` (`mb_strlen(fragment)×1,3`, min. 200 — żeby model nie dostał przyzwolenia „30 tys. znaków" na krótki akapit), rozmiar fragmentu `config('ai.chunk_chars')`=2500 → `data-ai-chunk` na przycisku, postęp „Poprawiam… 3 z 12" (przy 1 fragmencie: sekundy), częściowy sukces = poprawione fragmenty + reszta w oryginale + uczciwy toast. Kliknięcie = 1 użycie `data-ai-uses` niezależnie od liczby fragmentów. Fragmenty lecą SEKWENCYJNIE (celowo — nie równolegle). Plan zostawiony niżej jako zapis decyzji.

## Po co

Czas korekty AI jest zdominowany przez PRZEPISYWANIE CAŁEGO TEKSTU na wyjściu — rośnie liniowo z długością pola, nie z trudnością. Pomiar na realnym opisie produktu (2045 znaków): 4 próby dały 32,5 / 23,4 / 15,2 / 26,2 s. Dłuższy tekst przebije timeout 120 s i skończy się toastem „usługa niedostępna".

**Stan faktyczny na 2026-07-26 (sprawdzony w bazie, żeby nie panikować na zapas):** najdłuższa strona CMS ma 1920 znaków, mediana 904, ŻADNA nie przekracza 4000 — dziś wszystko działa. Limit 30 000 z `config('pages.content_max')` to teoria. ALE Rafał: „musimy założyć, że ktoś doda 10 000 znaków lub więcej" — te „Regulaminy" po 573 znaki to zalążki; prawdziwy regulamin z RODO i odstąpieniem to 15–20 tys. znaków.

## Decyzja architektoniczna

**Dzielić po stronie PRZEGLĄDARKI, nie serwera.** Gdyby serwer dostawał całość i ciął w pętli, jedno żądanie HTTP dalej trwałoby minuty i timeout wróciłby tylnymi drzwiami. Przeglądarka tnie i wysyła kilkanaście krótkich żądań, każde tego rozmiaru, który już działa.

**Ujednolicenie wychodzi za darmo** — korekta AI ma już JEDEN punkt wejścia (sprawdzone): wszystkie trzy pola idą przez `<x-rich-editor>` → `<x-ai-improve-button>` → `resources/js/ai.js` → `AiController@improve`. Miejsca użycia: `seller/shop/edit.blade.php`, `seller/pages/form.blade.php`, `seller/products/form.blade.php`. Piszesz dzielenie RAZ w `ai.js` i mają je wszystkie pola; każde następne pole z `<x-rich-editor>` dostanie je automatycznie.

**Jedna ścieżka dla wszystkiego** (Rafał chce spójności): tekst ZAWSZE idzie przez dzielenie, krótki wychodzi jako jeden fragment. Bez rozgałęzienia „krótkie tak, długie inaczej" — dwa tryby by się z czasem rozjechały.

## Zakres

1. `resources/js/ai.js` — cięcie po blokach najwyższego poziomu przez `DOMParser` (Trix generuje płaską strukturę: div/h2/ul/li, więc granice są czyste), pętla po fragmentach, składanie z powrotem, obsługa fragmentu który padł (reszta ocalała — dziś awaria w 40. sekundzie kasuje całą pracę).
2. Pasek postępu „fragment 3 z 12" zamiast licznika sekund — uczciwszy, bo widać ile zostało. Zastępuje licznik dodany 2026-07-26.
3. `AiController` — przyjmuje fragment; walidacja długości per fragment, nie per całość.
4. **PUŁAPKA, KTÓRA WYSADZI WDROŻENIE, JEŚLI SIĘ O NIEJ ZAPOMNI:** trasa ma `throttle:30,1` (`routes/web.php` ~206). Jedna korekta = kilkanaście żądań, więc 3 poprawki × 12 fragmentów = 36 > 30 → użytkownik dostaje błąd limitu w połowie roboty. Przestawić licznik z „ile żądań" na „ile korekt". Uwaga: JS-owy `data-ai-uses` / `max_uses_per_field` (3) liczy KLIKNIĘCIA, więc on akurat zostaje poprawny.
5. Testy + build (patrz [[orphaned-build-processes-incident]] — build tylko w spokojnym momencie, ze sprzątaniem).

## Do przemyślenia przy pisaniu

- Model widzi fragment bez reszty strony — przy literówkach bez znaczenia, przy przepisywaniu stylu już tak.
- Cięcie nie może rozerwać zagnieżdżonej listy.
- Koszt się nie zmienia: te same tokeny, rozłożone na kilka żądań.

## Relacja do streamingu

Nie są konkurencją: dzielenie rozwiązuje **czy się uda**, [[plan-ai-streaming-response]] rozwiązuje **jak długo się gapisz w ekran**. Dzielenie jest PIERWSZE i ważniejsze — działa bez SSE, czyli bez ryzyka, że shared host zdusi bufory. Gdy fragmenty wpadają do edytora po kolei, tekst i tak przyrasta co kilka sekund, więc streaming wewnątrz fragmentu staje się opcjonalnym polerowaniem.

Powiązane: [[ai-task-profiles-architecture]], [[deepseek-ai-improve]], [[shared-hosting-constraints]].
