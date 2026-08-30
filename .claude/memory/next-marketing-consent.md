---
name: next-marketing-consent
description: "ZAMKNIĘTE — zbieranie zgód wdrożone 2026-07-15 (customer_consents 1:N po kanale, aktywacja + profil), link wypisu wdrożony 2026-07-29 razem z modułem wysyłki. Zostaje tylko ewentualny kanał SMS."
metadata: 
  node_type: memory
  type: project
  originSessionId: d43b718a-bf6f-4d34-bb7d-d0b76a72a4c6
  modified: 2026-07-29T19:25:21.275Z
---

**ZBIERANIE ZGÓD WDROŻONE 2026-07-15** (581 testów, ~30 min). Pilność zażegnana — zgody się gromadzą, więc każdy nowy klient może trafić do przyszłego mailingu.

## Stan wykonania
**Powstało:**
- Migracja `2026_07_15_200000_create_customer_consents_table` — `customer_id` (FK cascade), `channel`, `granted_at`, `revoked_at`, `version`, `ip_address`, **unique(customer_id, channel)**.
- `App\Enums\ConsentChannel` (jeden case `Email`; SMS = nowy case + wiersz, bez migracji i bez ruszania `customers`).
- `App\Models\CustomerConsent` (+`isActive()`), `Customer::consents()`, `Customer::hasConsent()`, `Customer::setConsent()`.
- `config/legal.php` → `marketing_consent.version` + `.text`. **Reguła: zmieniasz `text` → podbij `version`.**
- Checkbox na aktywacji (`storefront/auth/activate`, `ActivationRequest`, `ActivationController`); box „Wiadomości od sklepu" w profilu (`storefront/account/edit`, `MarketingConsentRequest`, `AccountController::consents()`, POST `/moje-konto/zgody`).
- `tests/Feature/Storefront/MarketingConsentTest.php` (8 testów).

**DWIE PUŁAPKI pilnowane testami — nie zepsuć przy refaktorze:**
1. **Nie przestemplowywać dowodu.** `AccountController::consents()` zapisuje TYLKO przy faktycznej zmianie stanu — inaczej każdy klik „Zapisz" nadpisywałby `granted_at`/`ip_address` na dzisiejsze i przepadałby dowód, KIEDY zgoda naprawdę padła (RODO art. 7 każe wykazać właśnie to). Test: `test_resaving_same_state_does_not_restamp_the_proof`.
2. **Zgoda PER SKLEP, nie per e-mail.** Ta sama osoba z tym samym adresem w dwóch sklepach ma dwie niezależne zgody — inaczej zgoda u jednego sprzedawcy byłaby wyciekiem bazy do drugiego. Test: `test_consent_is_per_shop_not_per_email`.

Konwencja: brak zgody = **brak wiersza** (≠ wypis); wypis zostawia wiersz z `revoked_at`.

**LINK WYPISU — WDROŻONY 2026-07-29** razem z modułem wysyłki (`ce02487`, szczegóły w [[plan-bulk-mail]]): podpisana trasa `/wypisz-sie/{customer}` BEZ wygaśnięcia, wypis natychmiast przy wejściu, przycisk cofnięcia przy pomyłce, stopka wyłącznie w korespondencji seryjnej. Zostaje ewentualny kanał SMS (nowy case enuma, bez migracji).

## Dlaczego było PILNE (kontekst decyzji)
**Zgoda nie działa wstecz.** Checkbox dodany za miesiąc nie obejmie klientów zarejestrowanych do tego czasu — a zgody nie da się dorobić inaczej niż pytając ich mailem, na który właśnie nie ma się zgody (błędne koło). **Każdy tydzień zwłoki = kolejni klienci, do których Pawilon nigdy nie będzie mógł napisać.** Dlatego to jedyny element [[plan-bulk-mail]], który MUSI powstać przed modułem wysyłki, a nie razem z nim. Sam moduł może poczekać dowolnie długo — zbieranie zgód nie.

Robota jest mała (kilka godzin z testami) — nieproporcjonalnie mała do kosztu zwłoki.

## Gdzie zbieramy — USTALONE (Rafał, 2026-07-15)
- **TYLKO dla zarejestrowanych. Zbieramy NA AKTYWACJI KONTA** (`storefront/auth/activate` — ekran, gdzie klient ustawia hasło po kliknięciu podpisanego linku). Rafał: „tam jest super miejsce — wiemy, że konto jest już aktywne".
  - **Dlaczego to mocne miejsce:** klient kliknął link ze SWOJEJ skrzynki, więc zgoda jest podparta dowodem weryfikacji adresu — praktycznie nie do podważenia. Jest też zaangażowany (kończy zakładanie konta), więc checkbox nie stoi na drodze do kasy.
- **Edycja w profilu klienta** (`storefront/account/edit`) — włączanie/wyłączanie w każdej chwili.
- **W KASIE NIE ZBIERAMY. Kasa gościa NIE daje zgody w ogóle** — pytanie zamknięte. Efekt uboczny (zdrowy): skoro tylko konta budują bazę, sprzedawca ma powód zachęcać do zakładania kont zamiast pchać wszystkich przez kasę gościa.

## Co zrobić
1. **Checkbox na ekranie aktywacji** — osobny od akceptacji regulaminu, **niezaznaczony domyślnie**, np. „Chcę otrzymywać informacje o nowościach". Zgoda musi być uprzednia i dobrowolna (art. 10 uśude; „zarejestrowany" ≠ „zgodził się na marketing").
2. **Przełącznik w profilu klienta** — ta sama dana, edytowalna.
3. **Zapis zgody z dowodem**: data, IP, wersja/treść zgody. Wzorzec gotowy w kodzie: `App\Services\ConsentRecorder` + `User::consents()` + `LegalDocument` (wersjonowane, idempotentne `firstOrCreate`, `accepted_at` + `ip_address`) — ale to obsługuje **sprzedawców (`User`)**. Zrobić **analogicznie dla `Customer`, jako osobny typ zgody** (marketing ≠ akceptacja regulaminu), nie podpinać pod istniejący. Uwaga: zgoda jest **per sklep** (klienci są per-sklep, patrz [[plan-customer-accounts]]) — zgoda u jednego sprzedawcy nie może oznaczać zgody u innego.
4. **Link szybkiego wypisu w KAŻDYM mailingu** — działający **bez logowania** (podpisana trasa, wzorzec jak aktywacja klienta). Zgoda ma być odwoływalna równie łatwo, jak udzielona. Wypis natychmiastowy.

Pliki na start: `resources/views/storefront/auth/activate.blade.php` + `App\Http\Requests\Storefront\ActivationRequest`, oraz `resources/views/storefront/account/edit.blade.php` + `App\Http\Requests\Storefront\ProfileUpdateRequest`.

**Nie dotyczy maili transakcyjnych** (potwierdzenia zamówień, zmiany statusu, „Napisz do klienta") — te idą bez zgody marketingowej, bo są niezbędne do wykonania umowy. Zgoda i wypis dotyczą WYŁĄCZNIE korespondencji seryjnej/handlowej. Nie dokładać linku wypisu do maili o zamówieniu.

**How to apply:** robić przy najbliższej okazji dotykania kasy lub rejestracji klienta, nie czekać na moduł mailingu. Powiązane: [[plan-bulk-mail]], [[plan-packages]] (korespondencja seryjna = Pawilon), [[plan-customer-accounts]], [[email-outbox-cron-pattern]].
