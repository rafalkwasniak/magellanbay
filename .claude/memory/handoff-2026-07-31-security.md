---
name: handoff-2026-07-31-security
description: "Handoff 31.07.2026 (wieczór): grafika OG centrali (+ konwersja na JPG) i utwardzenie formularzy publicznych po feedbacku testera. Commity af7a412, 7771903, 9701535. Testy 1222 → 1230."
metadata: 
  node_type: memory
  type: project
  originSessionId: 5c4f5743-5e78-4788-bcf6-2be5ad5638e4
  modified: 2026-07-31T19:35:24.840Z
---

Sesja z dwóch niezależnych wątków, oba zgłoszone przez ludzi z zewnątrz — nie znalezione przez nas.

## 1. Grafika OG centrali (`af7a412`, `7771903`)

Rafał zrobił grafikę i spytał, gdzie ją wgrać. Okazało się, że **centrala nie miała żadnych znaczników Open Graph** — storefronty sprzedawców mają je od 27–28.07, sama platforma nie.

- Grafika w `public/images/` pod nazwą = skrót MD5 zawartości (Facebook cache'uje po adresie, więc nowa wersja musi mieć nową nazwę). Ścieżka w `config/seo.php`, czytana przez `Seo::platformImage()`.
- Znaczniki na landingu (+ brakujący `canonical`) i w `layouts/public` (regulamin, PP).
- **PNG 1066 KB → JPG 165 KB.** Konwersja świadomie BEZ podpróbkowania chrominancji (`-sampling-factor 4:4:4`): pomarańczowy tekst na jasnym tle to najgorszy przypadek dla domyślnego 4:2:0. Porównanie wycinka w powiększeniu 2× nie pokazało różnicy.
- `PlatformOgTest` pilnuje: plik z configu istnieje, ma 1200×630, waży < 400 KB.

## 2. Utwardzenie formularzy publicznych (`9701535`)

Znajomy Rafała po obejrzeniu Kramio: „zabezpiecz logowanie captchą albo blokadą po nieudanych próbach, bo produkcje WP są nieustannie męczone".

**Analiza pokazała, że pytał o rzecz, którą już mieliśmy — a obok była realna dziura.** Wniosek metodyczny: feedback z zewnątrz warto potraktować jako ZAPROSZENIE DO PRZEGLĄDU CAŁEJ OKOLICY, a nie jako zgłoszenie do odhaczenia. Gdyby zrobić dokładnie to, o co prosił, dziura zostałaby nietknięta.

- ✅ **Już było:** blokada logowania do centrali (5 prób / 5 min, klucz e-mail+IP), pokryta testem sprawdzającym rzecz najważniejszą — że po blokadzie nie wchodzi nawet POPRAWNE hasło.
- ⚠️ **Dziura:** `POST /rejestracja` w centrali **bez żadnego limitu**, a wysyła mail aktywacyjny na dowolny adres = darmowa maszynka do zalewania cudzej skrzynki. Koszt płacony reputacją domeny → maile transakcyjne wszystkich sprzedawców lecą w spam. Storefront miał `throttle`, centrala nie.
- Progi w `config/security.php` → `public_forms` (`register`, `activation`), nazwane limitery w `AppServiceProvider`.
- Honeypot na rejestracji **zamiast captchy**. Uzasadnienie odrzucenia captchy: zewnętrzny skrypt = kolejny podmiot w polityce prywatności + zależność w CSP + tarcie w formularzu, którym wchodzą sprzedawcy („sklep w 15 minut").
- Logowanie klienta w sklepie: blokada per KONTO (klucz e-mail+shop_id+IP), nie tylko limit per IP. Wspólna mechanika w traicie `App\Http\Requests\ThrottlesLogins`.
- Zdarzenie `Lockout` było wywoływane, ale **nikt go nie słuchał** → `App\Listeners\ReportLockout` wysyła alert na Discorda (e-mail maskowany: `ja***@example.com`). Zweryfikowane na żywo, webhook zwrócił 204, Rafał potwierdził alerty.
- `DiscordErrorReporter` dostał metodę `alert()` obok `report()` — ten sam webhook, kolor bursztynowy zamiast czerwonego.

## Gotcha, który się prawie wydarzył

Honeypot chowałem najpierw klasami Tailwinda. `grep` po `public/build/assets/*.css` wykazał, że **`left-[-9999px]` i `w-px` NIE MA w zbudowanym CSS** → pole byłoby widoczne dla ludzi w środku formularza rejestracji. Przestawione na styl wpisany w znacznik, bo ukrycie jest tu wymogiem poprawności, nie dekoracją. Potwierdza [[tailwind-classes-must-exist-in-build]] — i pokazuje, że warto grepować ZANIM się uzna rzecz za zrobioną.

## Stan

Testy **1222 → 1230**, wszystko zielone. Wszystko zweryfikowane na produkcji (nie tylko w suicie). Build CSS niepotrzebny.

## Wykryte przy okazji, NIE zrobione

**Brak funkcji „zapomniałem hasła"** w całej aplikacji → [[open-no-password-reset]]. Pilne, bo wchodzą testerzy.

Powiązane: [[plan-seo-audit]] (tam dopisana luka „audyt objął storefronty, nie centralę"), [[priorities-launch-first]].
