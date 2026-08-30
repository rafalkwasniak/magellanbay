---
name: legal-audit-2026-08-15
description: "Audyt regulaminu i polityki (15.08) skillem commercial-legal-pl: co zrobione, co czeka na jedno wspólne wydanie v3, co do prawnika."
metadata: 
  node_type: memory
  type: project
  originSessionId: 4d8bfafa-24ef-47c8-8b0f-9bb8f207d0e5
  modified: 2026-08-15T16:56:53.040Z
---

Audyt obu dokumentów prawnych przeprowadzony 2026-08-15 przy użyciu skilla `commercial-legal-pl` (zainstalowany w `~/.claude/skills/`, klon git — `git pull` aktualizuje, `rm -rf` odinstalowuje). Apache 2.0, autor: Adam Piotrowski, kancelaria Żurawska Piotrowski. **README wymienia 8 workflowów, w katalogu jest 10** — m.in. `generator-regulaminu.md` z pełną ścieżką B2C e-commerce (art. 27/38/43a–43g/7a u.p.k.). README jest nieaktualny, nie ufać jego spisowi.

**Skill jest miejscami ZA nami:** jego `generator-regulaminu.md` każe wstawiać link do platformy ODR, a nasz §19 ust. 2 słusznie ją pomija (ODR zamknięta 20.07.2025). Nie pozwolić „naprawić" tego wstecz.

## ZROBIONE 15.08 (kod, wdrożone na produkcji)

Checkbox wyraźnego żądania rozpoczęcia świadczenia przed upływem 14 dni (art. 15 ust. 3 i art. 35 u.p.k.). Regulamin obiecywał to w §9 ust. 2 od 06.08, a kod nie zbierał — przy odstąpieniu konsument odzyskiwałby 100% mimo zapisu o rozliczeniu proporcjonalnym. Migracja `package_payments` (`immediate_start_at`, `immediate_start_ip`, `immediate_start_terms_version`) wykonana na produkcji.

## WYDANIE v3 OPUBLIKOWANE 2026-08-15, commit `320df46`

Regulamin i polityka mają w bazie wersję 3 (`legal_documents`). Wszystkie 3 konta sprzedawców czekają na ponowną akceptację — to normalne działanie `EnsureConsentsAreCurrent`, nie usterka.

**NUMERACJA REGULAMINU SIĘ ZMIENIŁA:** nowy §11 (zgłaszanie treści bezprawnych) wepchnął dawne §11–§19 na §12–§20. **Stare odesłania z notatek i handoffów sprzed 15.08 wskazują na inne paragrafy niż dziś.** Mapowanie najczęściej cytowanych: §13 (usunięcie Sklepu) → **§14**; §14 (odpowiedzialność) → **§15**; §15 (reklamacje) → **§16**; §17 (powierzenie art. 28) → **§18**; §18 (zmiany) → **§19**. §1–§10 bez zmian.

**Weryfikacja odesłań po przenumerowaniu:** skrypt sprawdził 20 paragrafów bez dziur i 14 odesłań — wszystkie trafiają w cel. Przy każdej kolejnej edycji regulaminu powtórzyć ten krok; to najczęstsza cicha usterka w edytowanych umowach.

**Komentarz nagłówkowy w plikach `docs/prawne/*.html` NIE trafia do bazy** — jest wycinany regexem przy publikacji. Niesie listę zmian i decyzje świadome, więc czytać go przed edycją.

## DECYZJE RAFAŁA (nie podnosić ponownie)

- **§15 ust. 2 zostaje dla WSZYSTKICH** — milczące uznanie reklamacji po 14 dniach także wobec B2B, choć ustawa wymaga tego tylko wobec konsumentów. „Lepiej w tę stronę wizerunkowo."
- **DeepSeek nie jest podprocesorem** — patrz [[deepseek-ai-improve]].

## DO SPRAWDZENIA

Polityka deklaruje przekazywanie danych do USA na podstawie EU-US Data Privacy Framework (Google, Discord). Google Ireland to podmiot unijny; **czy Discord Inc. faktycznie jest na liście DPF — niezweryfikowane**. Zapasowa podstawa (klauzule standardowe) jest wpisana, więc nie ma dziury, ewentualnie nadmiarowe zdanie.

## CZYSTE (nie audytować ponownie bez powodu)

Regulamin: wszystkie 9 odesłań międzyparagrafowych trafia w cel, wszystkie 11 definicji używanych (zero „zombie"), minimum art. 8 ust. 3 u.ś.u.d.e. spełnione, §17 ma komplet elementów art. 28 ust. 3 RODO. Polityka: retencja logów zgadza się z konfiguracją, podstawa cookies wskazuje Prawo komunikacji elektronicznej z 2024 (nie nieaktualne Prawo telekomunikacyjne).
