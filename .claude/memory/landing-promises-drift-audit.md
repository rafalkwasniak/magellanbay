---
name: landing-promises-drift-audit
description: "Audyt obietnic na kramio.pl (2026-08-08): landing dryfuje za kodem. Siatka funkcji przeniesiona do PackageFeatures::highlights() z plakietką pakietu liczoną z configu, żeby ten błąd nie wracał."
metadata: 
  node_type: memory
  type: project
  originSessionId: e03d2a67-a20c-47fc-82f6-de09ee8ca64e
  modified: 2026-08-08T10:47:26.072Z
---

**Rafał 2026-08-08: „sprawdź obietnice klientom na kramio.pl".** Commit `6e1dca9`, testy 1416 → 1420.

## Wzorzec, nie incydent
Landing to **jedyne miejsce w projekcie, które nie ma testu na zgodność z rzeczywistością**, więc cicho zostaje w tyle po każdym wdrożeniu. Audyt wyłapał moduły działające od tygodni, o których nie było ani słowa: mapa strony i Search Console (03.08), nadawanie paczek InPost z etykietą (07.08), cała warstwa wyglądu sklepu (szablony, palety, kolor, [[plan-shop-character-axes]]), kody rabatowe, edycja zamówień, analityka, kartoteka klientów.

To bliski krewny lekcji „funkcja dla SKLEPÓW, nie dla centrali" ([[handoff-2026-07-31-analytics]]): **przy każdym wdrożeniu pytać osobno, czy landing ma o tym mówić.**

## Trzy obietnice, które kłamały
1. **„Pakiet zmienisz w każdej chwili"** (2 miejsca) — nieprawda dla zejścia niżej. `PackageUpgrade::downsize()` wpuszcza tańszy pakiet dopiero w oknie `shop.subscription.notice_days` (30 dni) przed końcem okresu; na darmowy Kram schodzi się wyłącznie przez wygaśnięcie. Tekst mówi teraz osobno o górze i o dole, a liczbę dni czyta z configu.
2. **„Bez umów"** — kłóciło się z Regulaminem przy rejestracji i z własną umową sprzedawcy z Paynow, opisaną dwie sekcje wyżej.
3. **Kafelek wysyłki** obiecywał paczkomat i kuriera bez zastrzeżenia, a w Kramie `courier_shipping = false`.

## Ukryte limity, które kupujący poznawał dopiero po ścianie
- **Tygodniowa pula zadań AI (100/400/800)** — egzekwowana przez `AiQuota`, a `labels()` dawało wszystkim pakietom identyczną etykietę „Opisy z korektą AI", więc różnica znikała z porównania. Teraz liczba jest W etykiecie, dzięki czemu mechanizm `is_new` podświetla ją sam.
- **Google Analytics** — płatny i egzekwowany, nieobecny w żadnej karcie.

## Zabezpieczenie na przyszłość (to jest tu najważniejsze)
Siatka funkcji przeniesiona z `welcome.blade.php` do **`PackageFeatures::highlights()`**. Każdy kafelek ma opcjonalny `requires` = klucz uprawnienia; plakietka „od pakietu X" liczy się przez `cheapestWith()` z `config/shop.php`. **Kafelek nie może już obiecać w darmowym pakiecie czegoś płatnego** — dokładnie ten błąd miała karta wysyłki.

Testy-zapory w `LandingPackagesTest`: `test_landing_names_what_shipped_after_the_last_rewrite` (lista modułów, które MUSZĄ być wymienione), `test_gated_features_carry_the_package_badge`, `test_landing_states_the_downgrade_rule_honestly`, `test_package_cards_disclose_the_weekly_ai_pool`.

## Co przy okazji potwierdzone jako PRAWDA
Zakup pakietu online **działa** (`POST /pakiet/kup/{package}` → `PackagePaymentService`, klucze platformy Paynow ustawione na `production`) — docblock `PackageController` twierdził, że nie działa, i był nieaktualny. Ceny i przekreślenia liczą się z configu, limity produktów się zgadzają, zwroty faktycznie w każdym pakiecie, zero zmyślonych liczb ([[open-landing-fabricated-stats]] trzyma).

Powiązane: [[plan-packages]], [[pricing-packages]], [[plan-package-payments]].
