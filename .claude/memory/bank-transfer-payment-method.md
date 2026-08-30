---
name: bank-transfer-payment-method
description: "WDROŻONE: dane konta = dana w „Mój sklep”; „Przelew na konto” = fiszka w Ustawieniach; trójpodział dana/metoda/integracja."
metadata: 
  node_type: memory
  type: project
  originSessionId: fa4db0ba-efa6-4709-8c45-c76fa2098faf
---

WDROŻONE 2026-07-01 (commit e8c67bb). „Dane do przelewu" rozbite wg trójpodziału, który jest wzorcem dla kolejnych metod płatności/dostawy:

1. **DANE (fakty)** → „Mój sklep" (`seller.shop.*`, `ShopProfileRequest`). Numer konta, odbiorca, nazwa banku — typowane kolumny na `shops` (`bank_account_number` 26, `bank_account_holder`, `bank_name`). Sekcja „Dane do przelewu" na SAMYM DOLE formularza (za adresem) — blok NIP→dane firmy→adres zostaje w jednym ciągu, bo wypełnia się razem z pobrania z NIP. Numer normalizowany w `prepareForValidation` do 26 cyfr NRB (usuwa spacje + prefiks `PL`), reguła `digits:26` (bez sumy kontrolnej mod-97 — świadomie, dołożymy później).
2. **METODA (co widać w kasie)** → „Ustawienia" (`seller.settings.*`). Fiszka „Przelew na konto" = kolumna `bank_transfer_enabled` (bool, default true, cast boolean). Checkbox wyszarzony+`disabled` gdy brak numeru (hidden input zachowuje stan). `Shop::bankTransferAvailable()` = `bank_transfer_enabled && filled(bank_account_number)` — użyć przy checkoucie.
3. **INTEGRACJA (klucze + operator)** → zakładka „Integracje" (jeszcze nie ma; [[plan-shop-settings-storage]] kat.3, wyższe pakiety [[plan-packages]]).

Kluczowe rozróżnienie (ustalone z Rafałem): **przelew tradycyjny to JEDNA metoda z jednym numerem — NIE per-bank.** „Włącz mBank / PKO" ma sens dopiero dla płatności online (bramka PayU/P24/Autopay) i InPost — wtedy: skonfiguruj integrację → fiszka „Pokaż płatność online przez X" w Ustawieniach. To przyszłość, nie teraz.

Zasada jednego źródła: integracja trzyma połączenie+klucze; widoczność metody w kasie = fiszka w Ustawieniach (bez dublowania włączników).

Model helpery: `formattedBankAccountNumber()` (grupy po 4), `bankAccountHolderName()` (odbiorca ?? nazwa firmy). Testy: zapis konta w `ShopProfileTest`, fiszka w `ShopSettingsTest`. Zestaw 166/166.
