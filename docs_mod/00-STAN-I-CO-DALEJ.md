# Stan prac i co dalej

**Czytać PIERWSZE.** Ten plik opisuje, gdzie jesteśmy i co robić dalej. Pozostałe dokumenty w tym katalogu to plan sporządzony przed rozpoczęciem prac — kroki 1–3 są już wykonane, więc czyta się je jako uzasadnienie decyzji, nie jako listę zadań.

Data: 5 września 2026.

---

## Gdzie jesteśmy

Ta instalacja to **sklep dedykowany dla Magellan Bay**, odbity od Kramio i przerobiony na tryb jednego klienta. Pracuje pod `magellan.kwasniak.org` jako środowisko robocze; docelowo pojedzie na serwer klienta.

**Kroki 1–3 są zrobione i przeniesione także do Kramio**, bo są generyczne — to baza wielokrotnego użytku dla każdego kolejnego klienta dedykowanego, a nie robota pod Magellana.

| Krok | Co daje | Commit |
|---|---|---|
| 1 | Pakiet `dedicated` — brak limitów, wszystkie funkcje otwarte, nic nie wygasa | `93fb164` |
| 2 | Przełącznik `SHOP_MODE` — wygaszenie rejestracji, konsoli admina, pakietów, dwóch komend crona | `db60556` |
| 3 | Sklep na domenie głównej zamiast subdomeny, rozstrzygnięcie 9 kolizji adresów | `8003f21` |
| 5 | Seedery danych startowych — `DeploymentSeeder` i `DemoSeeder` | *bieżąca sesja* |

Suita: **1745 testów zielonych.** Zachowania trybu pilnuje `tests/Feature/DedicatedModeTest.php` — każde w parze „nie ma w dedykowanym" / „nadal jest w Kramio". Seedery pilnuje `tests/Feature/DeploymentSeederTest.php`.

---

## Co dalej — TYLKO w tym repozytorium

Od tego momentu prace **nie dotykają już Kramio**. Zostaje krok 4 plus właściwe zamówienie klienta:

### Krok 4 — marka klienta i dokumenty
Szczegóły w [04-branding-i-dokumenty.md](04-branding-i-dokumenty.md). Logo, favicon, tytuły stron, stopki maili, `config/company.php`, dokumenty prawne sklepu.

Stan cząstkowy:
- **Materiały graficzne** — czekają na barwy i logotypy klienta.
- **Regulamin sklepu** — jest kreator (`resources/views/seller/legal/templates/regulamin.blade.php`), trzeba go wypełnić pod Magellana. **Produkty personalizowane są wyłączone z prawa odstąpienia** (art. 38 pkt 3 u.p.k.) — kreator umie o to zapytać i musi dostać odpowiedź „tak".
- **Polityka prywatności sklepu** — nie istnieje w żadnej formie. Podstawą będzie nasza polityka z `docs/prawne/polityka-prywatnosci.html`, oczyszczona z warstwy platformy (pośrednik znika, zostaje sklep ↔ kupujący).
- **Treść maila aktywacyjnego** (`App\Services\ActivationMailer`) mówi językiem platformy: „dziękujemy za rejestrację", „postawisz swój sklep w kilka minut". W sklepie dedykowanym nikt się nie rejestrował. Do przepisania — **dlatego seeder wdrożeniowy wypisuje link aktywacyjny na konsolę, zamiast wysyłać ten mail.**

### Krok 5 — seeder wdrożeniowy — ZROBIONE

`DeploymentSeeder` zakłada konto właściciela i jedyny rekord sklepu; parametry czyta z `config/deployment.php` ← `.env` (klucze `DEPLOY_*`), więc **następne wdrożenie nie dotyka kodu seedera**. Pisany ręcznie, bez fabryk.

Odmawia startu w trzech sytuacjach — i to jest w nim najważniejsze:

| Sytuacja | Dlaczego to groźne |
|---|---|
| `SHOP_MODE` inny niż `dedicated` | puszczony na Kramio zakłada sprzedawcę z dożywotnim pakietem bez limitów, bez śladu w historii |
| w bazie jest już sklep | `ResolveShop` bierze `Shop::first()`, więc drugi sklep byłby **niewidoczny** |
| brak `DEPLOY_OWNER_EMAIL` lub nazwy sklepu | konto bez adresu = właściciel nigdy nie ustawi hasła |

Garda „druga instalacja" sprawdzona **na żywej bazie roboczej**: seeder odmówił, nic nie zapisał.

`DemoSeeder` dokłada 12 produktów (magnesy podróżnicze), 5 tagów i 2 zamówienia z policzonymi kwotami — żeby dało się pracować nad wyglądem i pokazać klientowi działający sklep. **Nie działa na `APP_ENV=production`.** Przed przekazaniem sklepu klientowi baza idzie od nowa.

### Etap 2 oferty — funkcje Magellan Bay
Personalizacja nadruku (formatki), grawerka rewersu, cena z czterech składników, koszyk rozpoznający konfigurację, partnerzy licencyjni z regułą nienakładania się, katalog w trzech podziałach, rozliczenia XLSX, wstrzymanie sprzedaży serii.

**Ważne przy pisaniu:** części generyczne (formatki, opcje z dopłatą, cena składana, koszyk per konfiguracja) projektować pod **kubek z imieniem**, nie pod magnes z logo maratonu. To one mają się kiedyś sprzedać kolejnym klientom. Kartoteka licencjodawców i raporty rozliczeniowe zostają bespoke.

---

## Stan instalacji roboczej

| | |
|---|---|
| Adres | https://magellan.kwasniak.org |
| Katalog | `/home/host473413/domains/magellan.kwasniak.org` |
| Baza | `host473413_magellan` — użytkownik **nie ma** dostępu do bazy Kramio |
| Logowanie właściciela | `/sprzedawca/logowanie` · `magellan@kwasniak.org` |
| PHP | `/opt/alt/php85/usr/bin/php` (domyślne `php` w shellu to 8.3) |
| `origin` | katalog Kramio — źródło poprawek |
| `github` | `git@github.com-magellanbay:rafalkwasniak/magellanbay.git` |

### Bezpieczniki — NIE ZDEJMOWAĆ bez powodu

Instalacja stoi na **tym samym serwerze co produkcja Kramio**, a to dokładnie sytuacja, przez którą kasowaliśmy katalog `shop.kwasniak.org` (patrz `CLAUDE.md`, nagłówek i sek. 6.5).

- **Brak wpisu w cronie** — najważniejsze. Bez `schedule:run` nie wyjdzie ani jeden mail, nie ruszy kolejka, nie zapyta InPostu.
- **Klucze Paynow, Fakturowni i InPostu puste** — Fakturownia nie ma sandboxa, każde żądanie tworzy **realny dokument**.
- **Osobny webhook Discorda** — alerty stąd nie zlewają się z Kramio.
- `BACKUP_ENABLED=false`, `APP_ENV=staging`, `APP_DEBUG=false`.
- Poczta wychodząca przez konto Kramio, ale maile i tak czekają w outboksie na crona, którego nie ma.

---

## Rzeczy niedomknięte

**Konsola admina dla niezalogowanego zwraca 302 do logowania, nie 404.** Zalogowany właściciel dostaje 404 i to jest przypadek, o który chodziło. Laravel sortuje middleware po własnej liście priorytetów, na której `Authenticate` wyprzedza nasze; `prependToPriorityList` zadziałało w testach, ale nie na serwerze i nie ustaliliśmy dlaczego (opcache sprawdzony — waliduje co 2 sekundy, więc to nie to). W teście jest to opisane **bez asercji obiecującej więcej, niż aplikacja robi**.

**Limity poza pakietem** — `description_max`, `product_description_max`, `ai.max_uses_per_field`, `homepage_promoted_limit` zostały na wartościach Kramio. Przejrzeć z klientem po wgraniu katalogu, nie zgadywać teraz.

---

## Zasady, o których łatwo zapomnieć

- **Commity bez stopek generatora.** Autor to wyłącznie Rafał Kwaśniak, tożsamość ustawiona per-repo.
- **Testy filtrowane w trakcie pracy**, pełna suita raz przed commitem — konto ma limit 250 procesów.
- **Nigdy fabryk na bazie produkcyjnej** (incydent 16.08: konto z hasłem `password`).
- **Pełną ścieżkę do PHP 8.5** przy artisanie i composerze.
- Pamięć asystenta jedzie w repozytorium: `.claude/memory-sync.sh save` przed commitem po sesji z handoffem.
- `docs_mod/` i `.claude/` **wycinamy z artefaktu wdrożeniowego** przed przekazaniem klientowi — patrz [SETUP.md](SETUP.md).
