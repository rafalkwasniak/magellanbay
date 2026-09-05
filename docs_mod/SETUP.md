# Uruchomienie bazy na nowym serwerze

Archiwum zawiera **kod aplikacji i zbudowane zasoby front-endu**. Nie zawiera i zawierać nie może: `.env`, `vendor/`, `node_modules/`, plików z `storage/app/` ani historii gita.

Poniżej komplet kroków od rozpakowania do działającego sklepu.

---

## 1. Rozpakowanie i uprawnienia

```bash
unzip kramio-baza.zip -d /ścieżka/do/sklepu
cd /ścieżka/do/sklepu
chmod -R 775 storage bootstrap/cache
```

Document root domeny ustawić na podkatalog **`public`** (nie `public_html`).

## 2. Zależności PHP

```bash
composer install --no-dev --optimize-autoloader
```

Wymagane PHP ≥ 8.3. Uruchamiać **jawnie właściwą wersją**, bo domyślne `php` w shellu bywa starsze niż to spod WWW:

```bash
/ścieżka/do/php85 $(which composer) install --no-dev --optimize-autoloader
```

## 3. Konfiguracja

```bash
cp .env.example .env
php artisan key:generate
```

Uzupełnić w `.env`:

| Klucz | Wartość |
|---|---|
| `APP_NAME` | nazwa sklepu klienta |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<domena-klienta>` |
| `APP_DOMAIN` | `<domena-klienta>` |
| `SHOP_MODE` | `dedicated` — o ile przełącznik został już wprowadzony (dok. 02) |
| `DB_*` | baza klienta |
| `MAIL_*` | SMTP klienta |
| `DISCORD_WEBHOOK_URL` | **osobny kanał dla tej instalacji**, nie kanał Kramio |
| `BACKUP_ENABLED` | `true` |

Klucze Paynow, Fakturowni i InPostu **nie idą do `.env`** — wpisuje je właściciel w panelu, są szyfrowane per sklep.

## 4. Baza danych

```bash
php artisan migrate --force
php artisan db:seed --class=DeploymentSeeder --force
php artisan storage:link
```

Szczegóły seedera: `docs_mod/06-dane-startowe.md`. **Bazę zakładamy z migracji, nigdy ze zrzutu produkcji Kramio** — tamten zawiera dane osobowe innych sprzedawców i ich klientów.

## 5. Front-end

Zbudowane zasoby są w archiwum (`public/build`), więc sklep ruszy bez Node. Przebudowa potrzebna dopiero przy zmianach w CSS lub JS:

```bash
npm ci && npm run build
```

Gdyby build padał na ciasnej maszynie: `RAYON_NUM_THREADS=1 npm run build`.

## 6. Cron — warunek konieczny

Jeden wpis, pełną ścieżką do właściwego PHP:

```
* * * * * /ścieżka/do/php85 /ścieżka/do/sklepu/artisan schedule:run >> /dev/null 2>&1
```

Bez niego **po cichu** przestają działać: wysyłka wszystkich maili, kolejka (faktury), odświeżanie statusów przesyłek InPost, naliczanie terminu odstąpienia od daty doręczenia i kopie zapasowe. Żadnego komunikatu — sklep wygląda na sprawny.

## 7. Sprawdzenie

```bash
php artisan test                 # pełna suita
php artisan schedule:list        # widoczne zaplanowane zadania
php artisan about                # wersje, sterowniki, cache
```

Potem wejść na stronę główną, kartę produktu i panel po zalogowaniu.

---

## Zanim to trafi do klienta

- [ ] `.env` uzupełniony, `APP_DEBUG=false`
- [ ] `SHOP_MODE=dedicated`, limity zdjęte (dok. 01)
- [ ] Warstwa SaaS wyłączona — brak rejestracji, panelu platformy, ekranu pakietu (dok. 02)
- [ ] Sklep odpowiada na domenie głównej, bez subdomeny (dok. 03)
- [ ] Marka klienta zamiast Kramio wszędzie, gdzie widać (dok. 04)
- [ ] Cron działa, kopia zapasowa wykonana i **odtworzona próbnie**
- [ ] Dane demonstracyjne usunięte
- [ ] `docs_mod/` skasowany — to nasze notatki robocze, nie materiał dla klienta
