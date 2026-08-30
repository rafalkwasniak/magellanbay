---
name: legal-dsa-hosting-classification
description: "DECYZJA Rafała (15.08): Kramio = HOSTING w rozumieniu DSA, nie platforma internetowa. Cztery argumenty + linia, której nie wolno przekroczyć."
metadata: 
  node_type: memory
  type: project
  originSessionId: 4d8bfafa-24ef-47c8-8b0f-9bb8f207d0e5
  modified: 2026-08-15T16:47:48.217Z
---

**Rafał zdecydował 2026-08-15: przy pracach prawnych wychodzimy z założenia, że Kramio jest dostawcą HOSTINGU w rozumieniu DSA (rozporządzenie 2022/2065), a nie platformą internetową.** Wymaga potwierdzenia przez prawnika, ale to jest nasza pozycja wyjściowa, nie pytanie otwarte.

**Cztery argumenty Rafała:**
1. Nie mamy strony, która spinałaby sprzedawców i ich oferty (brak agregatora).
2. Sklepy nie widzą się nawzajem i się nie reklamują.
3. To odpowiednik sytuacji, w której każdy klient dostaje kopię plików i sam stawia sobie sklep.
4. Udostępniamy miejsce i działający kod — nie działamy w imieniu sprzedawcy.

**Kod potwierdza argumenty 1 i 2 (zweryfikowane 15.08):** trasa `/sklepy` istnieje wyłącznie w grupie administratora; mapa strony centrali zawiera trzy adresy (landing, regulamin, polityka), zero sklepów — każdy storefront ma własną mapę na własnym hoście. To stan faktyczny do pokazania, nie deklaracja.

**LINIA, KTÓREJ NIE WOLNO PRZEKROCZYĆ:** §11 ust. 2 regulaminu daje nam licencję na prezentowanie Sklepu „w materiałach promujących Serwis". Przykładowe realizacje w marketingu są OK. **Galeria „sklepy działające na Kramio" z linkami na kramio.pl zabija argument nr 1** — jeśli kiedyś ktoś ją zaproponuje, to jest moment na świadomą decyzję prawną, nie na zwykły ficzer.

**Dlaczego to ważne:** kwalifikacja przesądza o art. 30–32 DSA (identyfikowalność przedsiębiorców — weryfikacja dokumentów sprzedawców, udostępnianie konsumentom). To byłby osobny moduł, nie paragraf. Sekcja 3 (art. 20–28) i tak nas nie dotyczy przez wyłączenie dla mikro/małych (art. 19).

**Art. 16 i 17 obowiązują NIEZALEŻNIE od kwalifikacji** — nie mają wyłączenia dla mikroprzedsiębiorców.

**MODUŁ WDROŻONY 2026-08-15, commit `aa806a5`.** Publiczny formularz `/zglos-tresc` na centrali (link w stopce storefrontów, landingu i stron platformy przez `Central::url()`), tabela `content_reports`, numer sprawy `ZG-000042` z `id`, trzy maile z outboxu (potwierdzenie / rozstrzygnięcie / uzasadnienie do sprzedawcy przy uznaniu), dział „Zgłoszenia" w panelu admina z odznaką. Obieg przetestowany na produkcji end-to-end — maile faktycznie doszły.

**Decyzje Rafała przy tym module:** sprzedawca NIE widzi zgłoszeń w swoim panelu (dostaje tylko mail przy uznaniu); ekran decyzji BEZ przycisków moderacyjnych („na razie minimalnie" — akcje w dziale Sklepy); ŻADNEGO terminu rozpatrzenia w regulaminie (niedotrzymanie własnej deklaracji gorsze niż jej brak); osobny adres `naruszenia@kramio.pl` (skrzynka jest catch-all).

**ZOSTAŁO:** nowy § w regulaminie (art. 14 + 16) i rozszerzenie §13 ust. 2 o elementy art. 17 — idzie w wydaniu v3, patrz [[legal-audit-2026-08-15]].

**Powód ważniejszy niż zgodność:** §14 ust. 1 („Operator nie odpowiada za treści Sprzedawców") stoi na wyłączeniu odpowiedzialności z art. 6 DSA, które działa dopóki nie wiemy o bezprawnej treści, a gdy się dowiemy — działamy niezwłocznie. Dziś wiedzę można nam przekazać mailem, a procedury nie ma. Notice & action utrzymuje §14 przy życiu.

Powiązane: [[legal-audit-2026-08-15]], [[plan-package-payments]].
