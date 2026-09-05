# 02 — Wyłączenie warstwy SaaS

Cel: klient loguje się i widzi **panel swojego sklepu**. Nigdzie nie ma śladu platformy, pakietów, rejestracji ani innych sprzedawców.

Wszystko poniżej **wyłączamy, nie kasujemy** — patrz zasada naczelna w [README](README.md).

---

## 2.1. Przełącznik trybu

Jeden klucz, od którego zależy reszta dokumentu. Proponowany kształt w `config/shop.php`:

```php
/*
| Tryb pracy aplikacji.
|   'saas'      — Kramio: wielu sprzedawców, pakiety, rejestracja, subdomeny
|   'dedicated' — sklep jednego klienta na jego serwerze
| Trybu NIE zmienia się na działającej instalacji.
*/
'mode' => env('SHOP_MODE', 'saas'),
```

W `.env` sklepu dedykowanego: `SHOP_MODE=dedicated`. W `.env.example` — `SHOP_MODE=saas`, żeby zachować parytet (FOUNDATION sek. 5).

Warto dołożyć pomocnik, żeby nie rozsiewać po kodzie porównań tekstowych — np. `App\Support\Mode::dedicated(): bool`. Jedno miejsce do zmiany, jeśli kiedyś dojdzie trzeci tryb.

---

## 2.2. Trasy do wyłączenia

Wszystkie w [`routes/web.php`](../routes/web.php). Wyłączać **warunkiem wokół grupy**, nie kasowaniem linii.

### Rejestracja sprzedawcy — [`:113–123`](../routes/web.php#L113)

```
GET  /rejestracja
POST /rejestracja
GET  /rejestracja/potwierdzenie
POST /rejestracja/wyslij-ponownie
```

W sklepie dedykowanym konto właściciela zakłada się raz, seederem wdrożeniowym. Publiczna rejestracja jest niepotrzebna i stanowi ryzyko — to formularz wysyłający maile na dowolny adres.

**Uwaga:** aktywacja konta ([`:127`](../routes/web.php#L127)) **zostaje** — używamy jej do ustawienia pierwszego hasła przez właściciela.

### Landing platformy — [`:77`](../routes/web.php#L77)

Trasa `/` na domenie centrali pokazuje stronę Kramio z cennikiem. W trybie dedykowanym `/` to **strona główna sklepu** (patrz [03-adresy-i-domena.md](03-adresy-i-domena.md)).

### Panel administratora platformy — [`:191–260`](../routes/web.php#L191)

Cała grupa `role:admin`. Działy: Sklepy, Sprzedawcy, Wiadomości, Pakiety, Zamówienia (przekrój platformy), Zgłoszenia DSA, Ustawienia platformy, Analityka platformy, Podgląd maili.

**Żaden z nich nie ma sensu przy jednym sklepie** — to narzędzia operatora platformy, nie właściciela sklepu. Wyłączyć grupę w całości.

Rolę `admin` zostawiamy w [`UserRole`](../app/Enums/UserRole.php) — przyda się nam do wejścia serwisowego, tylko bez ekranów.

### Pakiety u sprzedawcy — grupa [`:267+`](../routes/web.php#L267)

Wyłączyć trasy ekranu „Mój pakiet" (kontroler [`Seller/PackageController`](../app/Http/Controllers/Seller/PackageController.php)): przegląd pakietu, zakup, przedłużenie, zmiana na niższy.

### Usunięcie własnego sklepu — [`:280`](../routes/web.php#L280)

Ekran „usuń mój sklep" z karencją 7 dni to funkcja RODO **platformy**, chroniąca sprzedawcę przed kliknięciem. Klient na własnym serwerze kasuje sklep, kasując pliki i bazę. Ekran wyłączyć — inaczej właściciel może sobie samodzielnie zgasić sklep i uruchomić `shops:purge`.

### Webhook opłat za pakiety — [`:138`](../routes/web.php#L138)

Trasa przyjmująca powiadomienia Paynow dla **konta platformy**. Nie dotyczy sklepu klienta. Wyłączyć.

**Uwaga:** webhook Paynow **sklepu** ([`:133`](../routes/web.php#L133)) zostaje — to nim płacą klienci sklepu.

---

## 2.3. Menu i ekrany

Wyłączenie trasy nie usuwa odnośnika. Przejrzeć i schować pod tym samym warunkiem:

- **Menu panelu sprzedawcy** — pozycja „Mój pakiet" (wg pamięci ustawiona zaraz pod „Mój sklep"), pozycja prowadząca do usunięcia sklepu
- **Pulpit sprzedawcy** ([`Seller/DashboardController`](../app/Http/Controllers/Seller/DashboardController.php)) — kafelki i paski wykorzystania limitów: „produkty 12/24", „zadania AI 30/100". Przy limicie miliona pasek postępu jest bez sensu
- **Ekrany zamknięte pakietem** — komunikaty typu „ta funkcja jest dostępna od pakietu Stragan" z odnośnikiem do zakupu. Przy otwartych uprawnieniach nie powinny się pokazać, ale warto sprawdzić, czy któryś nie jest wyświetlany bezwarunkowo
- **Integracje** — ekran wspomina o pakietach przy Paynow i Google Analytics
- **Stopka i pasek górny panelu** — logo i nazwa Kramio (patrz [04-branding-i-dokumenty.md](04-branding-i-dokumenty.md))

Ekran zgód (`ensure.consents`) — sprawdzić, czy nie wymusza zgody sprzedawcy na oferty handlowe od Kramio. Ta zgoda w trybie dedykowanym nie ma adresata.

---

## 2.4. Cron

Plik [`routes/console.php`](../routes/console.php). Osiem wpisów, dwa wypadają:

| Komenda | Kiedy | W trybie dedykowanym |
|---|---|---|
| `email:dispatch` ([:13](../routes/console.php#L13)) | co minutę | **zostaje** — cała poczta sklepu |
| `queue:work` ([:19](../routes/console.php#L19)) | co minutę | **zostaje** — faktury, zadania w tle |
| `shipments:refresh` ([:27](../routes/console.php#L27)) | co minutę | **zostaje** — statusy przesyłek InPost |
| `shipments:refresh --deliveries` ([:33](../routes/console.php#L33)) | co godzinę | **zostaje** — doręczenia, start terminu odstąpienia |
| `subscriptions:check` ([:38](../routes/console.php#L38)) | 06:10 | **WYPADA** — przypomnienia o wygasającym abonamencie |
| `backup:run` ([:51](../routes/console.php#L51)) | 2×/dobę | **zostaje** — kopie bazy |
| `backup:check` ([:59](../routes/console.php#L59)) | 09:00 | **zostaje** — strażnik kopii |
| `shops:purge` ([:64](../routes/console.php#L64)) | 06:20 | **WYPADA** — kasowanie sklepów po karencji |

`subscriptions:check` przy `comped = true` i tak nie miałby co wysłać, ale lepiej go nie uruchamiać niż liczyć na to, że nic nie znajdzie. `shops:purge` jest wręcz groźny — to jedyna komenda, która kasuje sklep razem z historią sprzedaży.

Powiązane klasy zostają w repozytorium nietknięte: [`CheckSubscriptions`](../app/Console/Commands/CheckSubscriptions.php), [`SubscriptionLifecycle`](../app/Services/SubscriptionLifecycle.php), [`PackagePaymentService`](../app/Services/PackagePaymentService.php), modele `PackageChange`, `PackagePayment`, `SubscriptionNotice`.

---

## 2.5. Maile i powiadomienia

Przejrzeć szablony pod kątem treści, które przy jednym kliencie nie mają sensu:

- przypomnienia o wygasającym pakiecie (14/7/1 dnia przed) — nie wyślą się, skoro komenda nie chodzi, ale warto potwierdzić
- faktura Kramio za pakiet — [`GeneratePackageInvoice`](../app/Jobs/GeneratePackageInvoice.php)
- mail powitalny i aktywacyjny — treść mówi o platformie, do przepisania (dok. 04)
- mail pożegnalny przy usuwaniu sklepu — funkcja wyłączona, ale szablon zostaje

Zgoda sprzedawcy na informacje handlowe od Kramio ([`seller-marketing-consent`](../app/Models/UserMarketingConsent.php)) — w trybie dedykowanym nie ma nadawcy takich treści. Nie zbierać.

---

## Sprawdzian etapu

- [ ] `/rejestracja` zwraca 404
- [ ] `/administrator/panel` zwraca 404 lub przekierowanie, bez ujawniania konsoli
- [ ] W menu panelu nie ma „Mój pakiet" ani usunięcia sklepu
- [ ] Pulpit nie pokazuje pasków wykorzystania limitów
- [ ] `php artisan schedule:list` nie zawiera `subscriptions:check` ani `shops:purge`
- [ ] Logowanie prowadzi wprost do pulpitu sklepu
- [ ] Pełna suita testów przechodzi (testy warstwy SaaS muszą być świadome trybu — patrz niżej)

> **Uwaga o testach.** Suita ma dziś ~1713 testów i sporo z nich dotyczy pakietów, rejestracji i panelu platformy. Po wprowadzeniu przełącznika muszą one **wymuszać tryb `saas`** w konfiguracji testowej, a nie polegać na domyślnym. Osobno warto dopisać garść testów dla trybu `dedicated`: brak tras, brak limitów, otwarte uprawnienia.
