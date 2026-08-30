---
name: seller-marketing-consent
description: "Zgoda SPRZEDAWCY na informacje handlowe od Kramio — WDROŻONA 2026-07-30 (7bfdd84). Osobna tabela user_marketing_consents. Rafał wyłapał brak: nie dało się legalnie napisać do własnych sprzedawców. ZOSTAŁO narzędzie wysyłki w panelu admina."
metadata: 
  node_type: memory
  type: project
  originSessionId: 44c6a637-7dbd-4da5-ba49-0f65951d2764
  modified: 2026-08-10T19:40:39.682Z
---

**WDROŻONE 2026-07-30, commit `7bfdd84`.** Rafał sam zweryfikował lukę: *„musze jako właściciel mieć możliwość napisania do wszystkich… ale by to zrobić, muszę mieć już teraz ich zgodę"*.

## Co było
Przy rejestracji sprzedawcy zbieraliśmy TYLKO Regulamin + Politykę prywatności. `user_consents` trzyma akceptacje DOKUMENTÓW (obowiązkowe, wersjonowane dokumentem, bez kanału, nieodwoływalne). Zgody na treści handlowe nie było wcale — więc mailing do sprzedawców byłby nielegalny.

## Rozwiązanie
Osobna tabela **`user_marketing_consents`** (świadomie NIE dopchnięta do `user_consents` — inny byt: dobrowolny, kanałowy, odwoływalny). Kształt skopiowany z `customer_consents` ([[next-marketing-consent]]): `channel`, `granted_at`, `revoked_at`, `version`, `ip_address`, unique(user_id, channel).

- `User::hasMarketingConsent()` / `setMarketingConsent()` — bliźniaki metod z `Customer`.
- Checkbox przy rejestracji: **niezaznaczony**, oddzielony kreską od obowiązkowych zgód, opisany „Nieobowiązkowe". Brak zgody NIE blokuje konta.
- Box „Wiadomości od Kramio" w profilu — włączenie/wycofanie w każdej chwili.
- Treść i wersja: `config('legal.seller_marketing_consent')`. **Zmieniasz `text` → podbij `version`.**
- Backfill w migracji: 4 konta założycieli dostały zgodę (za wyraźną zgodą Rafała, konta testowe).

## Dwie pułapki (te same co u klientów — pilnowane testami)
1. **Brak wiersza ≠ wypisanie się.** Niezgoda nie tworzy rekordu; wycofanie zostawia `revoked_at`. Różnica dowodowa.
2. **Nie przestemplowywać dowodu.** Zapis TYLKO przy faktycznej zmianie stanu, inaczej każde „Zapisz" w profilu nadpisuje `granted_at`/`ip_address` na dzisiejsze i ginie informacja, KIEDY zgoda padła (RODO art. 7).

## Granica prawna, o której nie zapominać
Maile **niezbędne do wykonania umowy** — faktura za pakiet, wygaśnięcie abonamentu, awaria, zmiana regulaminu — idą **BEZ** tej zgody i nigdy nie wolno ich nią blokować. Zgoda dotyczy wyłącznie ofert: kody, promocje, „nowa funkcja, dokup pakiet".

## ✅ NIC NIE ZOSTAŁO — narzędzie wysyłki WDROŻONE 2026-08-10
Dział „Wiadomości" w panelu admina (commit `bfa23e7`): wybór odbiorców checkboxami, szukajka, link wypisu w stopce. Komplet w [[plan-platform-mailing]].

**Adresatów pytać WYŁĄCZNIE przez `User::activeMarketingConsent()`** (domknięcie do `whereHas()`), tak jak robi to lista sprzedawców i wysyłka. Własny warunek w zapytaniu to droga do wysłania oferty komuś wypisanemu — wycofana zgoda ZOSTAWIA wiersz w bazie.

Powiązane: [[next-marketing-consent]] (wzorzec u klientów), [[plan-bulk-mail]], [[plan-admin-panel-and-landing]].
