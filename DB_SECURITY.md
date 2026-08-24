# DB_SECURITY.md — ochrona produkcyjnej bazy przed przypadkowym wyczyszczeniem

Przenośna instrukcja dla projektów Laravel. Cel: **żadna komenda `artisan` (ręczna,
skryptowa ani auto-zatwierdzona przez asystenta) nie może wyczyścić ani zresetować
produkcyjnej bazy.** Realne dane bywają nie do odzyskania — traktujemy to jak zawór
bezpieczeństwa, nie jak sugestię.

## Kontekst — jak baza „wystrzeliła w kosmos"

Gdy `APP_ENV=local`, a domyślne połączenie wskazuje produkcyjny serwer DB, komendy
niszczące dane lecą **bez pytania o potwierdzenie**:

```
php artisan migrate:fresh      # usuwa WSZYSTKIE tabele i migruje od zera
php artisan migrate:refresh    # rollback + ponowna migracja
php artisan migrate:reset      # cofa wszystkie migracje
php artisan migrate:rollback   # cofa ostatnią partię
php artisan db:wipe            # usuwa wszystkie tabele, kropka
```

Wystarczy jedno takie wywołanie w katalogu wpiętym w produkcyjne MySQL — i danych nie ma.

---

## Warstwa 1 (KLUCZOWA) — twardy blok w kodzie

Blokuje destrukcyjne komendy **nawet z `--force` i nawet po potwierdzeniu promptu**.
Laravel ma to wbudowane od wersji 11.

`app/Providers/AppServiceProvider.php`, w metodzie `boot()`:

```php
use Illuminate\Support\Facades\DB;

public function boot(): void
{
    // migrate:fresh/refresh/reset/rollback + db:wipe rzucają wyjątkiem
    // na produkcji — nie do obejścia flagą --force ani potwierdzeniem.
    // W środowisku testowym (APP_ENV=testing) blok jest wyłączony, więc
    // RefreshDatabase (migrate:fresh na sqlite :memory:) działa normalnie.
    DB::prohibitDestructiveCommands($this->app->environment('production'));
}
```

Co NIE jest blokowane: zwykłe `php artisan migrate` (addytywne migracje przy deployu)
działa dalej. Blok dotyczy wyłącznie komend, które kasują/cofają dane.

## Warstwa 2 — `APP_ENV=production`

Warstwa 1 uruchamia się tylko przy `environment('production')`. Dlatego produkcyjny
serwer MUSI mieć:

```dotenv
# .env na serwerze produkcyjnym
APP_ENV=production
APP_DEBUG=false
```

Ustaw `APP_ENV=production` również w `.env.example`, żeby produkcyjna postawa (a więc
i twardy blok) była udokumentowanym stanem domyślnym wdrożenia. Efekt uboczny: Laravel
przywraca też natywne ostrzeżenie „Application In Production!" dla `migrate`.

## Warstwa 3 — guard w testach (sqlite-only)

Zabezpiecza ścieżkę testów: gdyby `phpunit.xml` kiedyś się zepsuł albo środowisko
podłożyło produkcyjne połączenie, suita **pada** zamiast tknąć produkcję.

`tests/TestCase.php`:

```php
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            $this->fail(
                "ABORT: tests resolved DB connection [{$driver}:{$database}] "
                .'instead of sqlite::memory:. Refusing to run to protect production.'
            );
        }
    }
}
```

Uzupełnij `phpunit.xml`, żeby testy zawsze celowały w izolowaną bazę:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
</php>
```

---

## Warstwa 4 (poza aplikacją) — najmocniejsza granica: uprawnienia usera DB

Kod można obejść; uprawnienia bazy nie. Dla **realnej** ochrony danych, których nie chcesz
stracić, produkcyjny użytkownik aplikacyjny powinien mieć uprawnienia do danych
(`SELECT/INSERT/UPDATE/DELETE`), ale odbierz mu `DROP`. Wtedy nawet `db:wipe` fizycznie
nie ma prawa usunąć tabel.

```sql
-- MySQL: user aplikacyjny bez prawa kasowania struktury
REVOKE DROP, ALTER ON nazwa_bazy.* FROM 'app_user'@'host';
FLUSH PRIVILEGES;
```

Uwaga: bez `ALTER`/`DROP` migracje zmieniające schemat wykonuj osobnym, uprzywilejowanym
kontem (ręcznie, świadomie), a nie kontem, którym chodzi aplikacja.

## Warstwa 5 — backupy (ostatnia linia obrony)

Bezpieczniki zawodzą; backup nie. Minimum:

- **Automatyczny dzienny zrzut** bazy (cron `mysqldump` lub snapshot hostingu),
  retencja min. 7 dni.
- **Zweryfikowane odtwarzanie** — przećwicz `restore` choć raz, żeby wiedzieć, że backup
  faktycznie działa.
- Zrzut **przed** każdą świadomą operacją na schemacie produkcyjnym.

---

## Warstwa 6 — dysk (pliki to też produkcja)

Baza to nie jedyne, co suita może skasować. Testy chodzą w katalogu produkcyjnym, więc
dysk `public` to te same zdjęcia produktów, loga i awatary, które widzą klienci.

**Incydent 2026-08-04:** nowe testy usuwania sklepu nie miały `Storage::fake('public')`.
`ShopEraser` kasuje katalogi po ID (`users/{id}`, `products/{id}`), a sqlite `:memory:`
rozdaje ID od 1 — suita skasowała realny `storage/app/public/users/1`, czyli awatar
administratora platformy. Zdjęcia klientów ocalały tylko przypadkiem: produkcyjne ID
produktów doszły już do 27, więc testowe „jedynki" nie miały w co trafić.

Lekcja jest ta sama co przy HTTP: **bezpieczeństwo nie może zależeć od tego, czy autor
testu pamiętał o fake'u.** `tests/TestCase.php::isolateDisks()` przestawia dyski `public`
i `local` na `storage/framework/testing/disks/*` przy każdym `setUp()` i **wywala suitę**,
jeśli korzeń dysku mimo to wskazuje poza piaskownicę. Pilnuje tego
`tests/Feature/StorageIsolationTest.php`.

Czego to **nie** załatwia (świadomy dług, do decyzji osobno):

- `ShopEraser` nadal kasuje **całe katalogi po ID**, a nie konkretne ścieżki z bazy —
  pomyłka w ID wciąż znaczy „znika folder", tyle że już nie z poziomu testów.
- Kasowanie jest **bezpowrotne** — nie ma kosza ani okna na cofnięcie pomyłki.
- **Nie ma backupu plików.** Warstwa 5 mówi o bazie; `storage/app/public` nie jest
  kopiowany żadnym cronem. Przy realnych klientach to jedyna kopia ich zdjęć.

---

## Warstwa 7 — fabryki nie działają na produkcji

Poprzednie warstwy pilnują, żeby nic z produkcyjnej bazy **nie zniknęło**. Ta pilnuje
czegoś odwrotnego: żeby nic się w niej **nie pojawiło** z fabryki.

**Incydent 2026-08-16 (wykryty 24.08):** ktoś wykonał `User::factory()->create()` na
produkcyjnym połączeniu — najpewniej w tinkerze, „tylko na chwilę". Została po tym
Klaudia Lewandowska, `skubiak@example.net`, rola **sprzedawca**, konto aktywne,
z domyślnym hasłem fabryki **`password`**. Przeżyło 8 dni, aż konsola admina pokazała
je jako „sprzedawca bez sklepu".

Warto zauważyć, czego **nie** było winne — bo pierwsza hipoteza była groźniejsza niż
prawda: to nie były testy (chodzą na sqlite `:memory:`, Warstwa 3) ani seeder (tworzy
sztywny `test@example.com`). Ślad dało dopiero hasło: `Hash::check('password', …)`
przeszło, a to sygnatura `UserFactory`.

Blokada w `AppServiceProvider::prohibitFactoriesInProduction()`: gdy `APP_ENV=production`,
resolver nazw fabryk rzuca wyjątkiem. Przechodzi przez niego każde `Model::factory()`
(żaden model nie ma własnego `newFactory()`), więc łapie wszystkie 12 fabryk naraz.
Fabryka służy do wypełniania bazy zmyślonymi danymi — na produkcji nie ma legalnego
zastosowania, więc blokada niczego nie odbiera.

Przy okazji rozbrojony `DatabaseSeeder`: szkielet Laravela trzymał w nim gotowe konto
`test@example.com` z hasłem `password`, czekające na przypadkowe `db:seed --force`.

Pilnuje tego `tests/Feature/Security/FactoryGuardTest.php` — sprawdza obie strony:
że na produkcji rzuca i że poza nią fabryki działają normalnie (gdyby garda złapała
środowisko testowe, przewróciłaby całą suitę).

**Weryfikacja na żywym środowisku:**

```bash
/opt/alt/php85/usr/bin/php artisan tinker --execute="try { App\Models\User::factory()->make(); echo 'GARDA NIE DZIAŁA'; } catch (RuntimeException \$e) { echo 'ok: '.\$e->getMessage(); }"
```

---

## Checklista wdrożenia w nowym projekcie

- [ ] `DB::prohibitDestructiveCommands($this->app->environment('production'))` w `AppServiceProvider::boot()`
- [ ] `APP_ENV=production` i `APP_DEBUG=false` w produkcyjnym `.env`
- [ ] `APP_ENV=production` w `.env.example`
- [ ] Guard sqlite-only w `tests/TestCase.php::setUp()`
- [ ] `phpunit.xml` wymusza `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`
- [ ] Guard dysków w `tests/TestCase.php::isolateDisks()` (Warstwa 6)
- [ ] Blokada fabryk na produkcji w `AppServiceProvider` + pusty `DatabaseSeeder` (Warstwa 7)
- [ ] Produkcyjny user DB bez `DROP`/`ALTER` (migracje schematu osobnym kontem)
- [ ] Automatyczny dzienny backup z przetestowanym odtwarzaniem

## Weryfikacja, że działa

Na produkcji (bez ryzyka — komendy mają zostać odrzucone, baza nietknięta):

```bash
php artisan db:wipe --force        # => "This command is prohibited...", exit 1
php artisan migrate:fresh --force  # => "This command is prohibited...", exit 1
php artisan test                   # => przechodzi na sqlite; guard nie blokuje
```

## Zasada operacyjna

Czyszczenie lub usuwanie tabel na produkcji robi **człowiek, ręcznie, w narzędziu bazy**
(np. phpMyAdmin) — nigdy przez `artisan` i nigdy przez asystenta. `php artisan migrate`
(nowe, addytywne migracje) przy deployu jest OK.
