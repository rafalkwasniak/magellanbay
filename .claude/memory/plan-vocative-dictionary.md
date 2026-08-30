---
name: plan-vocative-dictionary
description: "WDROŻONE 2026-07-15 — wołacz imion ze słownika w config/vocative.php; fallback = MIANOWNIK (nie \"Dzień dobry\"), zero zgadywania, zero API."
metadata: 
  node_type: memory
  type: project
  originSessionId: d43b718a-bf6f-4d34-bb7d-d0b76a72a4c6
---

**WDROŻONE 2026-07-15.** `config/vocative.php` (454 imiona) + `App\Support\Vocative` + `tests/Feature/VocativeTest.php` (14 testów). Podmienione 9 miejsc: 6× `OrderMailer`, `ActivationMailer`, `CustomerActivationMailer`, oraz widoki `layouts/panel`, `layouts/storefront`, `storefront/account/index`.

**Pokrycie zmierzone, nie zgadywane: 86,9% osób w PESEL.** Rafał dorzucił `docs/*.csv` (wykazy imion z rejestru PESEL, 74 tys. pozycji z liczbą wystąpień) — dzięki temu pokrycie jest liczone, a nie szacowane. Moja pierwotna szacunka („grubo ponad 90%") była zawyżona; pierwszy pomiar dał 83,8% i wykrył, że przeoczyłem Mikołaja (141 tys.), Dominika (137 tys.) i Wiesława (130 tys.). Po dopisaniu 64 polskich imion → 86,9%. Reszta braków to niemal wyłącznie imiona wschodnie (~2 mln osób) — zostają w mianowniku świadomie. Polskie wyczerpane do poziomu szumu (w całym rejestrze zostały 3 nieujęte imiona z diakrytykami >2000 osób, dopisane). **Skrypty pomiarowe były jednorazowe (scratchpad) — do przeliczenia po dopiskach napisać od nowa: iteruj CSV, `mb_strtolower`, porównaj z kluczami configu, waż `LICZBA_WYSTĄPIEŃ`.**

API: `Vocative::of(?string): ?string` (wołacz | mianownik | null) oraz `Vocative::greeting(?string): string` („Cześć Anno," | „Dzień dobry,") dla maili. Bierze pierwszy token, dopasowuje po `mb_strtolower`, uspokaja CAPS.

**Trzy decyzje, każda po dyskusji z Rafałem:**

1. **Słownik, nie reguły.** Wyjątki trafiają w najpopularniejsze imiona: Ola→Olu vs Kamila→Kamilo (ta sama końcówka -la, różny wołacz, bo pierwsze to zdrobnienie); Marek→Marku vs Paweł→Pawle (ruchome „e" w różnych miejscach); Maciek→Maćku (palatalizacja + ruchome e naraz).

2. **Fallback = MIANOWNIK, nie „Dzień dobry" — decyzja Rafała, wbrew mojej propozycji.** Proponowałem wycofanie do bezosobowego powitania przy braku w słowniku. Rafał: klientom nie zależy na odmianie, są przyzwyczajeni do „Cześć Anna" w większości sklepów — **utrata imienia jest gorsza niż brak odmiany**. Miał rację i to podwójnie: mianownik nigdy nie ośmiesza, więc daje bezpieczeństwo fallbacku I personalizację naraz. Skutek uboczny: słownik nie musi być kompletny (trafienie = „wow", pudło = poziom rynku), więc nie ma stresu o pokrycie ogona. `null` leci tylko gdy w polu nie ma imienia (pusto/cyfry/nazwa firmy z interpunkcją).

3. **Zero LLM w runtime.** Rafał proponował DeepSeek per konto z cache w tabeli. Odrzucone, bo cache karmiłby model WYŁĄCZNIE ogonem (popularne trafiają w słownik) — czyli dokładnie tym, w czym jest najsłabszy, i tam gdzie nikt nie zweryfikuje odpowiedzi. A ogon nie potrzebuje wołacza, bo ma darmowy poprawny fallback. LLM użyty raz, offline, do wygenerowania pliku — produktem jest artefakt w repo, nie zapytanie w ścieżce klienta.

**Why:** to nie jest „mały ficzer" tylko wzorzec — odmiana ma być pewna albo jej nie ma; nigdy zgadywana. Plik statyczny = zero utrzymania („raz a dobrze i zapominamy", cel Rafała), w przeciwieństwie do tabeli karmionej API, która jest żywym systemem do doglądania.

**How to apply:** dopisując imiona — klucz lowercase, wartość z wielkiej litery (test `test_dictionary_entries_are_well_formed` tego pilnuje). Nie dodawać reguł-heurystyk „na resztę". Gotcha z tej sesji: **Blade `@php(...)` inline wywala się na zagnieżdżonych nawiasach** (`@php($x = Foo::of($y->z))` kompiluje się do `<?php($x = …)` bez domknięcia i cała reszta widoku leci jako PHP) — używać blokowego `@php … @endphp`. Powiązane: [[open-mail-footer-contradiction]], [[naming-and-locale-convention]], [[text-truncation-preserves-words]].
