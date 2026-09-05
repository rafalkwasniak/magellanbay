# 04 — Marka klienta zamiast Kramio

Cel: nigdzie — w interfejsie, w mailach, w dokumentach, w kodzie źródłowym strony — nie pada słowo „Kramio". Klient kupił swój sklep, nie nasz produkt w jego barwach.

To jest praca żmudna, nie trudna. Warto ją zrobić raz porządnie i sparametryzować, żeby przy kolejnym kliencie sprowadzała się do podmiany kilku plików i wartości w `.env`.

---

## 4.1. Dane firmy

[`config/company.php`](../config/company.php) trzyma dziś dane **naszej** firmy:

```php
'name'        => 'Red Paprika Rafał Kwaśniak',
'address'     => 'Okrzei 73, 42-582 Rogoźnik',
'email'       => 'kontakt@kramio.pl',
'phone'       => '+48 668 196 229',
'abuse_email' => 'naruszenia@kramio.pl',
```

W trybie dedykowanym to muszą być dane **klienta** — trafiają na faktury, do regulaminu, do stopek maili i na strony informacyjne.

**Do rozstrzygnięcia:** część tych danych sklep trzyma już przy rekordzie `Shop` (`company_name`, `nip`, adres, `contact_email`, `contact_phone`) i to one idą na dokumenty sprzedażowe. `config/company.php` opisuje operatora platformy. W sklepie dedykowanym **operator i sprzedawca to ta sama firma**, więc najprościej wypełnić config danymi klienta i mieć spójność. Sprawdzić przy wdrożeniu, czy któreś miejsce nie miesza tych dwóch źródeł.

---

## 4.2. Pliki graficzne

| Plik | Co to |
|---|---|
| [`public/images/kramio-logo.png`](../public/images/kramio-logo.png) | logo w panelu i mailach |
| [`public/favicon.ico`](../public/favicon.ico) | ikonka karty przeglądarki |
| [`public/apple-touch-icon.png`](../public/apple-touch-icon.png) | ikona na ekranie startowym iPhone'a |

Podmienić na materiały klienta. Jeśli klient nie ma logotypu — to pozycja do ustalenia z nim przed startem, bo bez niego sklep wygląda na niedokończony, a my nie jesteśmy studiem graficznym (patrz zapis w ofercie).

**Ścieżkę do logo wyprowadzić do configu**, żeby przy następnym kliencie nie podmieniać pliku o cudzej nazwie.

---

## 4.3. Teksty w interfejsie

Miejsca, w których pada nazwa platformy:

- **Pasek górny i stopka panelu** — logo plus nazwa
- **Ekran logowania** i ekran aktywacji konta
- **Tytuły stron** (`<title>`) — dziś zawierają nazwę platformy
- **Karta na Facebooka (OG)** — grafika generowana z logo, opis
- **Ekran „Ciasteczka"** i teksty zgód
- **Komunikaty błędów i stron 404/500**

Najpewniejszą metodą jest przeszukanie repozytorium:

```bash
grep -rni "kramio" app/ resources/ config/ routes/ lang/ --include='*.php' --include='*.blade.php'
```

i przejście trafienie po trafieniu. Część z nich to komentarze w kodzie i nazwy własne w dokumentacji — te zostają. Liczy się to, co widzi użytkownik.

`APP_NAME` w `.env` ustawić na nazwę sklepu klienta — Laravel używa jej w kilku domyślnych miejscach.

---

## 4.4. Maile

[`MailBranding::for()`](../app/Support/MailBranding.php#L38) buduje tożsamość maila per sklep — logo, kolory, dane firmy w stopce. To już działa i przy jednym sklepie zadziała samo, o ile dane sklepu są uzupełnione.

[`MailBranding::system()`](../app/Support/MailBranding.php#L56) to tożsamość **platformy** — maile aktywacyjne, resety hasła, powiadomienia systemowe. W trybie dedykowanym musi zwracać to samo co `for()`, bo nie ma dwóch podmiotów.

Sprawdzić także:
- adres nadawcy `MAIL_FROM_ADDRESS` i `MAIL_FROM_NAME` w `.env`
- stopkę maili — dziś zawiera dane operatora platformy
- treść maila aktywacyjnego i powitalnego — mówią o zakładaniu sklepu na platformie

---

## 4.5. Dokumenty prawne

Tu jest istotna różnica w stosunku do Kramio, wynikająca z tego, że **znika pośrednik**.

| Dokument | W Kramio | W sklepie dedykowanym |
|---|---|---|
| Regulamin platformy | umowa Kramio ↔ sprzedawca | **nie dotyczy** — nie ma platformy |
| Polityka prywatności platformy | Kramio jako administrator danych sprzedawców | **nie dotyczy** |
| Regulamin sklepu | wzór dla sprzedawcy do wypełnienia | **wymagany** — umowa sklep ↔ kupujący |
| Polityka prywatności sklepu | wzór (do zrobienia) | **wymagana** |
| Moduł DSA (art. 16/17) | Kramio jako hosting | **nie dotyczy** — klient nie hostuje cudzych treści |

Mamy gotowy kreator regulaminu sklepu ([`resources/views/seller/legal/templates/regulamin.blade.php`](../resources/views/seller/legal/templates/regulamin.blade.php), 236 linii) wypełniany odpowiedziami — **zostaje i jest tu bardzo przydatny**.

Polityki prywatności dla sklepu **nie mamy** — trzeba przygotować. To pozycja wpisana do oferty jako „wdrożenie wzoru polityki prywatności i regulaminu sklepu", więc nie jest niespodzianką, ale wzór trzeba najpierw napisać.

Trasy dokumentów platformy ([`routes/web.php:150,153`](../routes/web.php#L150)) w trybie dedykowanym powinny pokazywać **dokumenty sklepu**, nie platformy.

---

## 4.6. Discord i alerty

Powiadomienia o błędach idą dziś na **nasz** kanał Discord (`DISCORD_WEBHOOK_URL`). W ofercie zapisaliśmy, że trafiają na kanał klienta.

Do ustalenia przy wdrożeniu: czy klient ma własny kanał, czy prowadzimy alerty u siebie w ramach gwarancji. **Jeśli u siebie — trzeba to rozróżniać**, bo inaczej awaria sklepu klienta wygląda w kanale identycznie jak awaria Kramio. To dokładnie ten problem, który mieliśmy ze starą instalacją `shop.kwasniak.org` (patrz `CLAUDE.md`, nagłówek).

Rekomendacja: **osobny webhook per instalacja**, a w treści alertu nazwa sklepu.

---

## Sprawdzian etapu

- [ ] `grep -rni "kramio" resources/views/` nie zwraca nic widocznego dla użytkownika
- [ ] Zakładka przeglądarki pokazuje nazwę sklepu klienta
- [ ] Logo, favicon i ikona na iPhone'a to materiały klienta
- [ ] Mail potwierdzający zamówienie ma dane klienta w stopce i jego logo
- [ ] Mail aktywacyjny nie wspomina o platformie
- [ ] `/regulamin` i `/polityka-prywatnosci` pokazują dokumenty sklepu
- [ ] Alert testowy trafia na właściwy kanał i daje się odróżnić od alertu Kramio
- [ ] Karta przy udostępnianiu linku na Facebooku pokazuje markę klienta
