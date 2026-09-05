# 06 — Dane startowe: migracje i seeder, nie zrzut

**Rekomendacja: `php artisan migrate` na pustej bazie + seeder wdrożeniowy. Bez zrzutu SQL z produkcji.**

---

## Dlaczego nie zrzut

### 1. Dane osobowe — argument rozstrzygający

Baza produkcyjna Kramio zawiera dziś:

| Tabela | Rekordów |
|---|---|
| `users` | 10 |
| `customers` | 2 |
| `orders` | 9 |
| `shops` | 9 |
| `email_messages` | 62 |

To są **dane realnych ludzi** — sprzedawców, ich klientów, adresy dostawy, treści maili. Wgranie tego na serwer innego podmiotu to udostępnienie danych osobowych bez podstawy prawnej.

Wycięcie sklepów nie wystarcza: `users` trzyma konta sprzedawców niezależnie od sklepu, `email_messages` treści z adresami, `user_consents` dowody zgód z IP i datą. Czyszczenie zrzutu tabela po tabeli jest wykonalne, ale **jedno przeoczenie to incydent** — a przy 40 tabelach przeoczenie jest kwestią czasu.

Pusta baza nie ma tego problemu w ogóle.

### 2. To ma być baza wielokrotnego użytku

Zrzut jest migawką jednej chwili. Seeder jest **procedurą**, którą uruchomisz przy każdym kolejnym kliencie, zmieniając kilka wartości. Skoro celem jest punkt wyjścia dla następnych zleceń, to seeder jest tym punktem, a nie plik `.sql`.

### 3. Wersjonowanie

Seeder mieszka w `database/seeders/` i jest w gicie razem z resztą kodu. Zrzut SQL nie należy do repozytorium — jest duży, binarny w praktyce i szybko się starzeje.

### 4. Spójność ze stanem migracji

Kramio ma **90 migracji**. Po wgraniu zrzutu tabela `migrations` musiałaby idealnie odpowiadać schematowi, inaczej pierwsze `php artisan migrate` na serwerze klienta albo nic nie zrobi, albo spróbuje utworzyć istniejące tabele. Świeża baza + `migrate` nie ma jak się rozjechać.

### 5. Czystość

Baza produkcyjna niesie ślady dwóch miesięcy pracy: rekordy testowe, osierocone wiersze, sklep skasowany w karencji, historię zmian pakietów. Nic z tego nie jest potrzebne u klienta i wszystko może mylić przy diagnozie.

---

## Kiedy zrzut miałby sens

Dla porządku — jedyny scenariusz, w którym warto go rozważyć, to **przenoszenie tej samej instalacji na inny serwer** (tak robiliśmy przy zmianie nazwy bazy 12.08). Tutaj nie przenosimy instalacji, tylko zakładamy nową.

Gdyby z jakiegoś powodu potrzebny był sam schemat bez danych, właściwą komendą jest `mysqldump --no-data` — ale `migrate` daje dokładnie to samo, tyle że powtarzalnie.

---

## Dwa seedery, dwa różne zadania

### `DeploymentSeeder` — idzie na serwer klienta

Minimum, żeby sklep dało się uruchomić i zalogować. **Pisany ręcznie, bez fabryk** (patrz gotcha niżej).

Zawartość:

1. **Konto właściciela** — rola `seller`, adres e-mail klienta, **bez hasła**; wysyłamy link aktywacyjny, właściciel ustawia hasło sam. Tak działa dziś aktywacja i nie ma powodu tego omijać.
2. **Rekord `Shop`** — nazwa, slug, dane firmy klienta (`company_name`, NIP, adres, `contact_email`, `contact_phone`), status publikacji.
3. **Pakiet i uprawnienia** — `comped = true` oraz `assignPackage('dedicated')`, w tej kolejności (patrz [01-tryb-dedykowany.md](01-tryb-dedykowany.md)).
4. **Ustawienia sprzedaży** — domyślna stawka VAT, włączone metody dostawy i płatności zgodnie z ustaleniami.
5. **Dokumenty prawne** — regulamin sklepu z kreatora i polityka prywatności, jako **szkice** do zatwierdzenia przez klienta (nie publikować za niego — to jego dokumenty i jego odpowiedzialność).

Parametry (nazwa sklepu, e-mail właściciela, dane firmy) czytać z `.env` albo z argumentów komendy, żeby przy następnym kliencie nie edytować kodu seedera.

### `DemoSeeder` — tylko u nas, nigdy u klienta

Dane do pracy i do pokazania klientowi na etapie testów:

- kilkanaście produktów z opisami i zdjęciami zastępczymi
- kategorie w trzech podziałach (rodzaj / tematyka / geografia — po zbudowaniu funkcji z Etapu 2)
- kilka grafik grawerek i przykładowy partner licencyjny
- jedno–dwa zamówienia w różnych statusach, żeby panel nie świecił pustką

**Uruchamiany wyłącznie w środowisku roboczym.** Po wgraniu prawdziwego katalogu przez klienta te dane muszą zniknąć — najprościej postawić bazę od nowa przed przekazaniem, niż kasować rekord po rekordzie.

---

## GOTCHA: żadnych fabryk na bazie produkcyjnej

**Incydent 16.08:** fabryka modelu uruchomiona na bazie produkcyjnej utworzyła konto z hasłem `password`. Od 24.08 broni przed tym Warstwa 7 `DB_SECURITY`, ale zasada zostaje:

- `DeploymentSeeder` pisany **ręcznie**, z jawnymi wartościami — bez `factory()`
- `DemoSeeder` może używać fabryk, bo chodzi tylko w środowisku roboczym; garda i tak go zablokuje, gdyby ktoś próbował inaczej

Przy okazji: `php artisan migrate --force` na cudzym serwerze **uzgadniać przed uruchomieniem**, nie po (incydent 04.08 — katalog produkcyjny to produkcja).

---

## Procedura na serwerze klienta

```bash
# 1. Pusta baza, dane w .env
php artisan migrate --force

# 2. Konto właściciela, sklep, pakiet, dokumenty
php artisan db:seed --class=DeploymentSeeder --force

# 3. Sprawdzenie
php artisan tinker --execute="
  \$s = App\Models\Shop::first();
  echo \$s->name.PHP_EOL;
  echo 'abonament aktywny: '.var_export(\$s->subscriptionActive(), true).PHP_EOL;
  echo 'limit produktow: '.\$s->entitlement('max_products').PHP_EOL;
"
```

Oczekiwane: nazwa sklepu klienta, `abonament aktywny: true`, `limit produktow: 1000000`.

---

## Sprawdzian etapu

- [ ] Baza klienta powstała z migracji, nie ze zrzutu
- [ ] `php artisan migrate:status` — wszystkie 90 migracji wykonane
- [ ] W bazie jest **jeden** sklep i **jedno** konto właściciela
- [ ] Żaden rekord nie pochodzi z Kramio — zero cudzych użytkowników, zamówień i wiadomości
- [ ] Właściciel dostał link aktywacyjny i ustawił własne hasło
- [ ] `Shop::first()->subscriptionActive()` zwraca `true`
- [ ] Dane demonstracyjne usunięte przed przekazaniem
