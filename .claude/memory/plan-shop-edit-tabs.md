---
name: plan-shop-edit-tabs
description: "DECYZJA (2026-06-29) — porzucamy zakładki NA stronie; rozbijamy konfigurację sklepu przez LEWE menu panelu na osobne podstrony."
metadata: 
  node_type: memory
  type: project
  originSessionId: ad2d01af-7396-446a-bc53-3263cd9693a7
---

**ZMIANA KIERUNKU (Rafał, 2026-06-29).** Pierwotny pomysł (zakładki/kafelki NAD boxami, jeden formularz przełączany JS-em) **odrzucony**. Zamiast budować drugie menu wewnątrz strony, wykorzystujemy **LEWE menu panelu sprzedawcy** — bo nie jest niczym ograniczone, a pogrupowane, czytelne dane to podstawa dobrego UX. „Mój sklep" rozbijamy na osobne pozycje menu / podstrony:

- **Wygląd** (pozycja już istnieje w lewym menu) → przenieść tu **logo** (box „Identyfikacja sklepu") + docelowo **kolory/szablony**. To naturalne miejsce na identyfikację wizualną.
- **Mój sklep** → zostaje z danymi podstawowymi/adresem (kategoria „profil i adres").
- **Ustawienia** (nowa pozycja) → m.in. **domyślny VAT sklepu** (kolumna w DB, default 23%; formularz produktu ma się nią prefillować zamiast sztywnego 23%).
- **Integracje** (nowa pozycja, później) → GA/PayU/InPost itd. (patrz [[plan-shop-settings-storage]] kat. 3, tabela `shop_integrations`).

Zasada: każda pozycja = osobna podstrona/formularz (nie jeden mega-formularz). Spójnie z [[ui-design-direction]] (warm boutique) i [[frontend-stack-decision]] (Blade + punktowo JS/Livewire).

Status (2026-06-29): **Wygląd ZROBIONE** (logo przeniesione, `seller.appearance.*`, commit 2d843f9). **Ustawienia ZROBIONE** (domyślny VAT, `seller.settings.*`, commit c60c30b). Zostało: **Integracje** (#4, osobny split — `shop_integrations`) oraz docelowo kolory/szablony na stronie „Wygląd" i edycja regulaminu.

**Integracje — konkret potwierdzony (Rafał, 2026-07-02):** pierwszym polem zakładki ma być **box na identyfikator Analytics** (np. Google Analytics measurement/tracking ID). To realny, konkretny wyzwalacz do zbudowania Integracji + `shop_integrations` ([[plan-shop-settings-storage]] kat.3). Storefront ma potem wstrzykiwać ten ID w layout (`x-layouts.storefront`), analogicznie do tokenów motywu.

**Integracje ZROBIONE (2026-07-03):** pozycja 🔌 „Integracje" w lewym menu, box GA (GA4 + GTM) z walidacją, storefront wstrzykuje kod, włącznik w Ustawieniach. Szczegóły techniczne w [[plan-shop-settings-storage]]. GA dostępne we wszystkich pakietach (decyzja Rafała). Zostało z tej notatki już tylko: kolory/szablony na „Wygląd" i edycja regulaminu.
