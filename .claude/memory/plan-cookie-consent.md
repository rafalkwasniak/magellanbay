---
name: plan-cookie-consent
description: "NASTĘPNY TEMAT (Rafał 31.07 wieczorem: 'wolałbym zrobić to szybciej niż pozostałe rzeczy, idealnie jutro'). Baner zgody na cookies — analiza gotowa, 8 pytań otwartych do rozstrzygnięcia przed kodem. Sedno: banery są DWA (centrala + storefronty), a blokada musi być SERWEROWA."
metadata: 
  node_type: memory
  type: project
  originSessionId: 5c4f5743-5e78-4788-bcf6-2be5ad5638e4
  modified: 2026-07-31T20:02:01.674Z
---

**Ustalone 2026-07-31 wieczorem.** Temat wypłynął sam: po wpięciu Google Analytics na centralę ([[handoff-2026-07-31-analytics]]) okazało się, że banera zgody nie ma nigdzie — ani na centrali, ani na storefrontach, które mierzą ruch od dawna. Rafał: *„wolałbym, by to zrobić szybciej niż pozostałe rzeczy, ale nie dzisiaj, bo już późno. Idealnie jutro."* **To stawia temat PRZED wysyłkami i przed panelem admina** → [[priorities-launch-first]].

## Sedno: problem jest w blokowaniu, nie w banerze

Baner, który się wyświetla, a skrypt i tak leci od razu, jest **gorszy niż brak banera** — dowodzi świadomości obowiązku przy jego niespełnieniu. Z tego wynika wymóg techniczny, który trzeba trzymać w głowie przez cały czas:

> **Blokada musi być SERWEROWA, nie w JavaScripcie.** Skrypt nie może pojawić się w wysłanym HTML-u, dopóki nie ma zgody. Zablokowanie go po stronie przeglądarki jest już za późno — plik został pobrany, Google zobaczyło żądanie.

Drugi wymóg, na którym wykłada się większość wdrożeń: **odmowa równie łatwa jak zgoda**. „Akceptuj" i „Odrzuć" na tym samym poziomie, jedno kliknięcie każdy. Odmowa schowana w „ustawieniach zaawansowanych" = wadliwe. Do tego musi istnieć sposób WYCOFANIA zgody później (zwykle link w stopce).

## Co u nas realnie wymaga zgody (sprawdzone w kodzie 31.07)

**Lista do zablokowania ma JEDNĄ pozycję: Google Analytics.** Sytuacja jest nietypowo czysta:

- ✅ **Fonty LOKALNE** (`public/fonts/`, Instrument Sans/Serif) — nie ma ładowania z serwerów Google, czyli nie ma najczęstszej wpadki audytów.
- ✅ **Zero bibliotek z CDN, wtyczek społecznościowych i pikseli reklamowych** w widokach.
- ✅ **Ciasteczka niezbędne** (sesja, koszyk, token CSRF, „zapamiętaj mnie") — zgody NIE wymagają. To większość tego, co aplikacja ustawia.
- ⚠️ **Google Analytics** — wymaga zgody. Centrala (`.env`) + per sklep (Integracje sprzedawcy).
- ❓ **Geowidget InPost** (`geowidget.inpost.pl`, tylko w kasie i tylko gdy sklep ma paczkomaty) — do rozstrzygnięcia, ale broni się jako FUNKCJONALNY: użytkownik właśnie poprosił o wybór paczkomatu. Jedyne miejsce, gdzie ktoś mógłby się spierać.
- Fakturownia / Paynow / InPost poza tym: zwykłe odnośniki albo połączenia z serwera, nie skrypty w przeglądarce.

## HACZYK: banery są DWA, nie jeden

Najważniejsza rzecz specyficzna dla Kramio, niewidoczna z zewnątrz:

- **`kramio.pl`** — administratorem danych jest Rafał, pomiar jest jego.
- **`{sklep}.kramio.pl`** — administratorem jest **SPRZEDAWCA**. To on wpisał własny identyfikator w Integracjach i to on odpowiada za zgodę swoich klientów. Sprzedawca nie ma jak sam wstawić banera, bo nie dotyka kodu storefrontu → **Kramio musi dostarczyć baner jako funkcję platformy, zbierającą zgodę W JEGO IMIENIU**.

Zgoda z centrali **NIE może** obowiązywać na storefroncie i odwrotnie — inny administrator, inny cel, inna domena.

**Argument sprzedażowy:** to domyka narrację, którą już prowadzimy („zgody, RODO, Omnibus, zwroty w tle" — dosłownie w tekście na FB z 31.07). Bez tego w obietnicy jest dziura.

## Punkt zaczepienia w kodzie

Sprzyja nam jedno: 31.07 pomiar wszedł **wspólnym komponentem** `resources/views/components/google-analytics.blade.php`, używanym i przez centralę, i przez wszystkie storefronty. **To JEDYNE miejsce, przez które Google wchodzi na stronę** — bramkę stawiamy tam raz, dla obu światów.

## 8 PYTAŃ OTWARTYCH — rozstrzygnąć Z RAFAŁEM przed pisaniem kodu

1. **Granularność:** tylko „Akceptuj / Odrzuć", czy kategorie (niezbędne / analityczne)? Przy JEDNEJ kategorii nieniezbędnej granularność wygląda na przerost — ale bywa oczekiwana.
2. **Baner na storefroncie bez GA:** sklep, który nie włączył pomiaru, ustawia wyłącznie ciasteczka niezbędne. Pokazywać baner mimo to (spójność), czy nie pokazywać (nie ma o co pytać)? Skłaniam się ku: NIE pokazywać.
3. **Czy sprzedawca może baner wyłączyć?** Propozycja: nie, gdy ma włączone GA — inaczej platforma firmuje naruszenie.
4. **Dowód zgody:** zapis w bazie (wzorzec `customer_consents`: data, IP, wersja treści) czy samo ciasteczko? Baza = koszt zapisu przy ruchu anonimowym; ciasteczko = brak dowodu. Rozważyć zapis TYLKO przy zgodzie, nie przy odmowie.
5. **Treść banera:** jednolita systemowa czy edytowalna per sklep? Edytowalna = ryzyko, że sprzedawca napisze bzdurę i to nas obciąży.
6. **Geowidget InPost** — funkcjonalny (bez pytania) czy za zgodą? Patrz wyżej.
7. **Gdzie link „Ustawienia cookies"** do wycofania zgody: stopka centrali i stopka storefrontu (ta druga musi respektować motywy).
8. **Czy przy okazji odświeżyć Politykę Prywatności** o sekcję cookies? Obecna deklaruje cele analityczne („np. Google Analytics"), więc wdrożenie jest z nią zgodne, ale sekcji o samych ciasteczkach nie ma.

## Warunki techniczne wykonania

- Storefront jest **Blade-first i JS-light** (bez `app.js`) → baner musi być lekkim vanilla JS, bez Livewire i bez zależności.
- Baner na storefroncie **respektuje motyw sprzedawcy** (tokeny CSS `--brand`, `--surface`, `--ink`) → [[plan-storefront-theming]].
- Ciasteczko zgody czytane **w PHP** przed renderem komponentu.
- Consent Mode od Google **świadomie pomijamy** — przydaje się przy kampaniach reklamowych i modelowaniu braków; my po prostu nie ładujemy skryptu bez zgody.
- Uwaga na [[tailwind-classes-must-exist-in-build]]: baner to nowy widok, więc albo klasy istnieją w buildzie, albo styl wpisany w znacznik (jak honeypot 31.07).

## Szacunek

~2 dni z testami. Centrala prosta (jeden identyfikator, trzy layouty). Storefronty większe: motywy, dowód zgody, brak JS-a.

**Zastrzeżenie:** to nie jest porada prawna. Podstawa to prawo komunikacji elektronicznej (od listopada 2024 zastąpiło art. 173 Prawa telekomunikacyjnego) + RODO. Kierunek jest ugruntowany, ale przy najbliższej okazji warto przepuścić przez prawnika razem z regulaminem.

Powiązane: [[plan-seo-audit]], [[next-marketing-consent]] (wzorzec dowodu zgody), [[frontend-stack-decision]].
