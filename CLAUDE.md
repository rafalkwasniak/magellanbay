# CLAUDE.md

Specyfika projektu **Magellan Bay** — sklep dedykowany. Plik czytany przez asystenta na starcie każdej sesji.

> ## TO NIE JEST KRAMIO
>
> Ten katalog wygląda jak Kramio, bo **jest odbity z Kramio** — ten sam kod, ta sama historia gita do commita `becaa1a`. Ale to osobne wdrożenie dla osobnego klienta, z własną bazą, własną domeną i wyłączoną warstwą platformy.
>
> Jeśli w jakimkolwiek pliku, komentarzu albo we własnej pamięci znajdziesz „kramio.pl", „centrala", „subdomena sklepu", „pakiety", „abonament" — **domyślnie NIE dotyczy to tej instalacji.** Sprawdź w kodzie, zanim na tym oprzesz działanie.
>
> Produkcja Kramio stoi **na tym samym serwerze**, w katalogu `/home/host473413/domains/kramio.pl`. Nigdy nie uruchamiaj tu niczego, co mogłoby jej dotknąć.

**Relacja do pozostałych plików:**

| Plik | Co opisuje |
|---|---|
| `FOUNDATION.md` | uniwersalne zasady współpracy — obowiązują zawsze |
| **`CLAUDE.md`** (ten plik) | specyfika tej instalacji — ma pierwszeństwo w kwestiach projektowych |
| `docs_mod/00-STAN-I-CO-DALEJ.md` | **czytać jako drugie** — gdzie jesteśmy i co dalej |
| `docs_mod/01`–`06` | plan przeróbki sporządzony przed pracami; kroki 1–3 wykonane |
| `docs/specyfikacja.md` | specyfikacja **Kramio**, nie tej instalacji — traktować jak historię |

---

## 1. Czym jest ta instalacja

**Sklep dedykowany dla klienta Magellan Bay** — magnesy podróżnicze z personalizacją. Jeden właściciel, jeden panel, jedna domena, bez rejestracji, pakietów i abonamentu.

**Ale to nie jest robota pod jednego klienta.** Celem jest **baza wielokrotnego użytku** — punkt wyjścia dla każdego kolejnego zlecenia „sklep na własnym serwerze". Stąd zasada naczelna:

> ### Konfiguracja, nie wycinanie
>
> **Niczego nie usuwamy z kodu.** Warstwa SaaS — rejestracja, pakiety, abonament, landing, konsola platformy — zostaje w repozytorium i jest **wyłączana konfiguracją**. Wielonajemczość zostaje **uśpiona**: jeden rekord `shops`, `shop_id` wszędzie jak dziś. Klient nigdy się o niej nie dowie.
>
> Powody: (1) drugi klient to nowe wdrożenie tej samej bazy, nie ta sama praca od zera; (2) łatka bezpieczeństwa i zmiana API dostawcy robi się raz; (3) wyrywanie `shop_id` ze ścieżki pieniędzy to tygodnie roboty tam, gdzie błąd kosztuje najwięcej, przy zysku czysto kosmetycznym.

### Co klient zamówił

Zakres podzielony na dwa etapy:

- **Etap 1 — licencja na silnik + wdrożenie.** Postawienie instancji, wyłączenie warstwy platformy, marka klienta, dane startowe. To jest ta przeróbka.
- **Etap 2 — funkcje Magellan Bay.** Personalizacja nadruku (formatki), grawerka rewersu, cena składana z czterech części, koszyk rozpoznający konfigurację, partnerzy licencyjni z regułą „nie sumujemy, liczy się wyższa", katalog w trzech podziałach, rozliczenia XLSX, wstrzymanie sprzedaży serii.

**Warunki handlowe (gwarancja, płatność, licencja) trzymamy w pamięci asystenta, nie w repozytorium** — patrz `[[plan-magellan-bay-separate-project]]`. Do pracy nad kodem potrzebne są z tego dwie rzeczy: klient **dostaje kod, ale nie wolno mu go zmieniać** (ingerencja = utrata gwarancji), oraz **zakaz odsprzedaży** kopii.

### Gdzie co trafia — reguła ustalona z góry

| Klient chce | Gdzie idzie |
|---|---|
| Inna bramka płatności | Wspólny kod, sterownik domyślnie wyłączony |
| Inny wygląd, sekcje, obrazki | Jego warstwa widoków. Zero kontaktu z Kramio |
| Zmiana w kasie | Najpierw jako opcja konfiguracyjna; jeśli się nie da — nadpisanie widoku |
| **Zmiana w logice koszyka/zamówienia** | **CZERWONA LAMPKA** — tędy idą pieniądze. Osobna wycena |

Przy pisaniu Etapu 2: części generyczne (formatki, opcje z dopłatą, cena składana, koszyk per konfiguracja) projektować **pod kubek z imieniem, nie pod magnes z logo maratonu** — to one mają się kiedyś sprzedać kolejnym klientom. Kartoteka licencjodawców i raporty rozliczeniowe zostają bespoke.

---

## 2. Stos i środowisko

- **Laravel Framework 13.17**, **PHP 8.5** na WWW, **Composer 2.9.7**, **Node 20.20 / npm 10.8**.
- **UWAGA na CLI:** domyślne `php` w shellu to **8.3**, nie 8.5. Zawsze jawnie:
  ```bash
  /opt/alt/php85/usr/bin/php artisan ...
  /opt/alt/php85/usr/bin/php $(which composer) ...
  ```
- **Document root** musi wskazywać na `…/magellan.kwasniak.org/public`, nie `public_html`.

### Stan instalacji roboczej

| | |
|---|---|
| Adres | https://magellan.kwasniak.org — **środowisko robocze**, docelowo serwer klienta |
| Katalog | `/home/host473413/domains/magellan.kwasniak.org` |
| Baza | `host473413_magellan` — użytkownik **nie ma** dostępu do bazy Kramio |
| Logowanie właściciela | `/sprzedawca/logowanie` · `magellan@kwasniak.org` |
| `APP_ENV` | `staging` (nie `production` — to jeszcze nie jest sklep klienta) |
| `origin` | `/home/host473413/domains/kramio.pl` — źródło poprawek |
| `github` | `git@github.com-magellanbay:rafalkwasniak/magellanbay.git` |

### Bezpieczniki — NIE ZDEJMOWAĆ bez powodu

Stoimy na tym samym serwerze co produkcja Kramio. To dokładnie sytuacja, przez którą 13.08 kasowaliśmy katalog `shop.kwasniak.org`: druga żywa kopia tej samej aplikacji, sięgająca na zewnątrz.

- **Brak wpisu w cronie** — najważniejszy. Bez `schedule:run` nie wyjdzie ani jeden mail, nie ruszy kolejka, nie zapyta InPostu.
- **Klucze Paynow, Fakturowni i InPostu puste.** Fakturownia **nie ma sandboxa** — każde żądanie tworzy realny dokument.
- **Osobny webhook Discorda** — alerty stąd nie mieszają się z alertami Kramio.
- `BACKUP_ENABLED=false`, `APP_DEBUG=false`.
- Poczta wychodząca przez konto Kramio, ale maile i tak czekają w outboksie na crona, którego nie ma.

**Przed uruchomieniem czegokolwiek, co pisze do bazy albo strzela na zewnątrz — sprawdź, w którym katalogu jesteś.**

---

## 3. Tryb dedykowany — jak działa

Sercem jest `SHOP_MODE` w `.env` (tutaj: `dedicated`; w Kramio: `saas`).

### `App\Support\Mode`

Pytamy **„czy to sklep dedykowany"**, nigdy „jaki mamy tryb":

```php
Mode::dedicated()   // sklep jednego klienta
Mode::saas()        // Kramio — domyślnie
```

Warunek czyta się wtedy jak zdanie i **domyślnie zachowuje się jak Kramio** — nowy ekran, o którym zapomnimy, zostanie widoczny w SaaS, a nie zniknie z niego po cichu.

### Trzy wykonane kroki

| Krok | Co daje | Commit |
|---|---|---|
| 1 | Pakiet `dedicated` — brak limitów, wszystkie funkcje otwarte, nic nie wygasa | `93fb164` |
| 2 | Przełącznik `SHOP_MODE` — wygaszenie rejestracji, konsoli admina, pakietów, dwóch komend crona | `db60556` |
| 3 | Sklep na domenie głównej zamiast subdomeny, rozstrzygnięcie 9 kolizji adresów | `8003f21` |

**Limity zdejmuje się danymi, nie kodem.** Wszystkie bramy schodzą się w `Shop::entitlement()`; `comped = true` na rekordzie sklepu sprawia, że `subscriptionActive()` zawsze zwraca `true` i nic nigdy nie wygasa. Nowy pakiet w configu + `comped` + `assignPackage('dedicated')` otwiera wszystko bez jednej linii zmiany w logice.

### `EnsureSaasMode` — dlaczego middleware, a nie `if` wokół tras

Gdyby trasy platformy **przestały istnieć**, każde `route('register')` w Bladzie rzuciłoby `RouteNotFoundException` i wywróciło całą stronę — a takich odwołań jest sporo. Middleware zostawia trasę i jej nazwę, tylko odpowiada **404**. Odnośniki chowamy osobno, w widokach.

**404, nie 403** — w sklepie dedykowanym te adresy mają nie istnieć. 403 mówiłby „to tu jest, ale nie dla ciebie" i zdradzał platformę pod spodem.

### Testy trybu

`tests/Feature/DedicatedModeTest.php` (312 linii) — każde zachowanie w **parze**: „nie ma w dedykowanym" / „nadal jest w Kramio". Bez tej pary test nie broni Kramio przed naszą przeróbką.

---

## 4. Kroki 1–3 wróciły do Kramio. Od kroku 4 — tylko tutaj

To ważne dla decyzji, gdzie pisać kod:

- **Kroki 1–3 są generyczne** i zostały przeniesione także do Kramio. To baza dla każdego kolejnego klienta dedykowanego.
- **Od kroku 4 (marka, dokumenty, seeder, funkcje Etapu 2) prace nie dotykają już Kramio.**

Wyjątek na przyszłość: gdyby przy Etapie 2 powstało coś naprawdę generycznego (silnik personalizacji), decyzję o przeniesieniu do Kramio podejmujemy **świadomie i osobno**, nie przy okazji.

---

## 5. Konwencje (odziedziczone z Kramio, obowiązują)

1. **Front:** Laravel + Blade + Livewire. Panel — Livewire w pełni. Storefront — **Blade-first** (SEO, szybkość) + Livewire punktowo tam, gdzie zarabia (koszyk).
2. **Brak publicznego JSON API.** Interaktywność przez Livewire i kontrolery. Konsekwencja: koperta `{success, message, data}`, OpenAPI i `api-guide.html` z `FOUNDATION.md` **nie dotyczą**.
3. **Locale:** pl-first, sklep jednojęzyczny. `APP_FALLBACK_LOCALE=en` to wyłącznie techniczna siatka bezpieczeństwa, nie drugi język produktu.
4. **Nazewnictwo: „Adres i interfejs — po polsku. Kod — po angielsku."**
   - PL: segmenty URL i slugi (`/produkt/132-magnes-gdansk`, `/koszyk`, `/sprzedawca/...`), teksty UI.
   - EN: modele, tabele, kolumny, enumy, **nazwy tras** (`products.show`), kontrolery, zmienne.
   - W testach i linkach zawsze `route('nazwa')`, nigdy sztywna ścieżka.
5. **Walidacja wyłącznie w Form Requestach**, cienkie kontrolery. Walidacja w JS (`forms.js`) to UX — źródłem prawdy jest Form Request.
6. **Stałe biznesowe w `config/`.** Każda zmiana w `.env` = aktualizacja `.env.example` **w tym samym kroku**.
7. **Strefa czasowa aplikacji: Europe/Warsaw.**

---

## 6. Zasady pracy, o których łatwo zapomnieć

- **Commity bez stopek generatora.** Autor to wyłącznie Rafał Kwaśniak, tożsamość ustawiona per-repo (`--local`, nigdy `--global`).
- **Testy filtrowane w trakcie pracy, pełna suita raz przed commitem** — konto ma limit 250 procesów.
- **Nigdy fabryk na bazie produkcyjnej** (incydent 16.08: konto z hasłem `password`). `DeploymentSeeder` pisany ręcznie, z jawnymi wartościami.
- **Testy nigdy nie strzelają do prawdziwych API** (incydent 30.07: ~46 realnych faktur w Fakturowni). `preventStrayRequests()` nigdy nie zdejmować.
- **Testy nigdy nie ruszają plików produkcji** (incydent 04.08).
- `migrate --force` na cudzym serwerze **uzgadniać przed uruchomieniem, nie po**.
- **Pamięć asystenta jedzie w repozytorium.** Po sesji z handoffem: `.claude/memory-sync.sh save`, potem commit.
- **Build robi Rafał.** Jeśli `npm run build` nie przechodzi — powiedzieć od razu po 1–2 próbach, nie walczyć.

---

## 7. Co wycinamy z artefaktu dla klienta

Klient dostaje kod. **Nie dostaje naszych notatek roboczych.** Przed spakowaniem archiwum wdrożeniowego usunąć:

- `docs_mod/` — plan przeróbki, nasze uzasadnienia i wątpliwości
- `.claude/` — pamięć asystenta (**także historia tego zlecenia i warunki handlowe**)
- `CLAUDE.md`, `FOUNDATION.md` — ten plik i zasady współpracy
- `docs/` — specyfikacja Kramio i dokumenty prawne **naszej** firmy
- `.git/` — historia zawiera commity Kramio sprzed odbicia

Lista kontrolna wdrożenia: `docs_mod/SETUP.md`.

---

## 8. Rzeczy niedomknięte

**Konsola admina dla niezalogowanego zwraca 302 do logowania, nie 404.** Zalogowany właściciel dostaje 404 i to jest przypadek, o który chodziło. Laravel sortuje middleware po własnej liście priorytetów, na której `Authenticate` wyprzedza nasze; `prependToPriorityList` zadziałało w testach, ale nie na serwerze i nie ustaliliśmy dlaczego (opcache sprawdzony — waliduje co 2 sekundy, więc to nie to). W teście opisane **bez asercji obiecującej więcej, niż aplikacja robi**.

**Limity poza pakietem** — `description_max`, `product_description_max`, `ai.max_uses_per_field`, `homepage_promoted_limit` zostały na wartościach Kramio. Przejrzeć z klientem po wgraniu katalogu, nie zgadywać teraz.

**Dokumenty prawne sklepu** — regulamin ma kreator (`resources/views/seller/legal/templates/regulamin.blade.php`), polityki prywatności **nie ma w żadnej formie**. Uwaga merytoryczna: produkty personalizowane są **wyłączone z prawa odstąpienia** (art. 38 pkt 3 u.p.k.) — to musi być w regulaminie Magellana i to jest pytanie, na które kreator już umie odpowiedzieć.

**Branding** — czeka na materiały klienta.
