# 05 — Wdrożenie na serwerze klienta

Cel: sklep stoi na serwerze klienta, działa i jest odebrany.

Zgodnie z ofertą: **środowisko robocze może stać u nas albo od razu u klienta**, ale docelowo sklep żyje na serwerze klienta i tam zostaje. Nasz serwer nie jest miejscem docelowym.

---

## 5.1. Wymagania serwera — sprawdzić PRZED startem

Lista jest w ofercie i wynika wprost z kodu:

| Wymaganie | Skąd | Uwaga |
|---|---|---|
| PHP ≥ 8.3 | [`composer.json`](../composer.json) `"php": "^8.3"` | produkcja Kramio chodzi na 8.5 |
| MySQL 8.0+ / MariaDB 10.6+ | `config/database.php` | |
| Rozszerzenia: mbstring, openssl, pdo_mysql, curl, dom, xml, fileinfo, tokenizer, zip | standard Laravela | |
| GD albo Imagick | [`ProductImageService`](../app/Services/ProductImageService.php), [`OgImageGenerator`](../app/Services/OgImageGenerator.php) | zdjęcia produktów i karta na Facebooka |
| Composer | | do instalacji i późniejszych prac |
| Dostęp SSH | | j.w. |
| Certyfikat SSL | | zwykły, **wildcard niepotrzebny** (patrz dok. 03) |
| SMTP | | sklep wysyła potwierdzenia i powiadomienia |
| Document root na `public/` | | nie `public_html` |
| **Cron co minutę** | [`routes/console.php`](../routes/console.php) | **warunek konieczny** |

Zależności produkcyjne są bardzo szczupłe — Laravel, Livewire, Tinker i nic więcej. To dobra wiadomość przy cudzym hostingu.

### Cron jest warunkiem koniecznym

Jeden wpis:

```
* * * * * /ścieżka/do/php /ścieżka/do/sklepu/artisan schedule:run >> /dev/null 2>&1
```

Bez niego **po cichu** przestają działać: wysyłka wszystkich maili, kolejka (faktury), odświeżanie statusów przesyłek InPost, naliczanie terminu odstąpienia od daty doręczenia oraz kopie zapasowe. Żadnego komunikatu, sklep wygląda na sprawny.

To pierwsza rzecz do sprawdzenia na cudzym serwerze i pierwsza do sprawdzenia, gdy „coś nie działa".

---

## 5.2. Instalacja

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # build można też wgrać gotowy
php artisan migrate --force
php artisan storage:link
php artisan config:cache route:cache view:cache
```

Uprawnienia do `storage/` i `bootstrap/cache/`.

**GOTCHA (pamięć, incydent 04.08):** katalog produkcyjny to produkcja. O `migrate --force` na cudzym serwerze pytać **od razu**, nie po fakcie.

**GOTCHA (Vite):** build potrafi się wywrócić na ograniczonej maszynie — `RAYON_NUM_THREADS=1` ratuje. Jeśli hosting klienta jest ciasny, prościej zbudować u siebie i wgrać `public/build/`.

---

## 5.3. `.env` — wartości specyficzne dla wdrożenia

```
APP_NAME="<nazwa sklepu>"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domena-klienta>
APP_DOMAIN=<domena-klienta>

SHOP_MODE=dedicated          # patrz dok. 02

DB_*                         # baza klienta
MAIL_*                       # SMTP klienta
MAIL_FROM_ADDRESS=<adres sklepu>

DISCORD_WEBHOOK_URL=         # osobny kanał dla tej instalacji (dok. 04)
BACKUP_ENABLED=true
```

Sekrety integracji — Paynow, Fakturownia, InPost — **wpisuje klient w panelu**, nie my w `.env`. Klucze są szyfrowane per sklep. My co najwyżej pomagamy przy konfiguracji (tak brzmi oferta: „pomoc przy podłączeniu").

**Każda zmiana `.env` = aktualizacja `.env.example` w tym samym kroku** (FOUNDATION sek. 5). Dotyczy zwłaszcza nowego `SHOP_MODE`.

---

## 5.4. Dane startowe

Sklep dedykowany nie ma rejestracji, więc konto właściciela i rekord sklepu trzeba założyć seederem wdrożeniowym. Powinien:

1. utworzyć użytkownika z rolą `seller` i wysłać link aktywacyjny (właściciel sam ustawia hasło),
2. utworzyć rekord `Shop` z danymi firmy klienta,
3. ustawić `comped = true` i `assignPackage('dedicated')` (dok. 01),
4. opublikować dokumenty prawne sklepu.

**GOTCHA (pamięć, incydent 16.08):** nie używać fabryk modeli na bazie produkcyjnej. Fabryka tworzy konto z hasłem `password`. Od 24.08 broni przed tym Warstwa 7 `DB_SECURITY`, ale seeder wdrożeniowy pisać ręcznie, bez `factory()`.

---

## 5.5. Kopie zapasowe

Mechanizm jest wbudowany i działa na cronie ([`backup:run`](../routes/console.php#L51) 2×/dobę, [`backup:check`](../routes/console.php#L59) o 09:00). Skonfigurować katalog docelowy i sprawdzić wolne miejsce.

Zgodnie z ofertą **nadzór jest po stronie klienta** — sklep zrobi kopię, ale ktoś musi patrzeć, czy ją zrobił. Strażnik `backup:check` wysyła alert; upewnić się, że trafia tam, gdzie klient go zobaczy.

Po pierwszym uruchomieniu warto **przećwiczyć odtworzenie** na kopii bazy. Robiliśmy to przy Kramio i to jedyny sposób, żeby wiedzieć, że kopie są coś warte.

---

## 5.6. Odbiór

Sprawdzian przed przekazaniem:

- [ ] `php artisan test` — pełna suita zielona **na serwerze klienta**
- [ ] Wszystkie punkty z sekcji „Sprawdzian etapu" w dokumentach 01–04
- [ ] Zamówienie testowe od początku do końca: koszyk → płatność → faktura → nadanie przesyłki → statusy → mail do klienta
- [ ] Nadanie **jednej realnej przesyłki** na koncie produkcyjnym InPost (to zaległość jeszcze z Kramio — konto produkcyjne nie było testowane)
- [ ] Płatność testowa przez Paynow na koncie klienta
- [ ] Faktura w Fakturowni klienta — **uwaga: brak sandboxa, każde żądanie tworzy realny dokument**
- [ ] Kopia zapasowa wykonana i odtworzona próbnie
- [ ] Alert testowy dotarł na właściwy kanał
- [ ] Wprowadzenie do panelu dla klienta: jak dodać produkt, formatkę, grawerkę i partnera

---

## 5.7. Po odbiorze

Zgodnie z ofertą:

- **gwarancja 12 miesięcy** na usterki — coś, co miało działać i nie działa
- **ingerencja klienta w kod = utrata gwarancji**; warto przy przekazaniu powiedzieć to na głos, nie tylko mieć w umowie
- utrzymanie, aktualizacje i reagowanie na zmiany u InPostu, Paynow i Fakturowni **nie są objęte** — to prace dodatkowe po 100 zł/godz.
- płatność: całość po odbiorze, faktura z 7-dniowym terminem

Warto zapisać sobie **stan wdrożenia**: wersję kodu (hash commita), datę odbioru i wersje integracji. Za rok, przy zgłoszeniu usterki, to jedyny punkt odniesienia — kod klienta nie jest już tym samym kodem co Kramio.
