---
name: cp-shorthand-commit-push
description: "Rafał's shorthand \"CP\" means do the full commit + push"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 95e26b90-4a53-4f6a-ad4d-f806f2cbc32c
---

When Rafał writes "CP" it means: perform the full commit AND push. This applies both when he says it standalone and when he answers "CP" to a question where I proposed committing/pushing — in that case do the whole thing (commit + push), not just confirm.

**Why:** he writes tersely to save time typing.

**How to apply:** on "CP", run the commit and the push in one go, following the project's git rules ([[shop-build-foundation]] / FOUNDATION sec. 3): per-repo identity `Rafał Kwaśniak <rafal@kwasniak.org>`, bulleted body, no generator footer/attribution ([[no-coauthor-footer-in-commits]]). Still propose a commit message first only if none has been discussed; otherwise just execute.

**Język commita (skorygowane 2026-07-15):** ta notatka mówiła wcześniej „English present-tense subject" za FOUNDATION sek. 3 — to była nieprawda i o mało nie wprowadziło mnie w błąd. Żywa praktyka: **subject po polsku, ze scope'em i dwukropkiem, rzeczownikowo** (`Zamówienia/statusy: ścieżka per (płatność × dostawa), …`), ciało punktowe po polsku z uzasadnieniem DLACZEGO tak, a nie inaczej (łącznie z odrzuconymi wariantami), zakończone liczbą zielonych testów. Scope = dział produktu (Zamówienia, Storefront, Konta klientów, Kasa), nie katalog w kodzie.

Historia przełomu: do 2026-07-05 angielski rozkazujący (`Add product management…`) → 07-05 polski rozkazujący (`Dodaj sprzedaż na wagę…`) → **od 2026-07-11 polski ze scope'em** i tak jest do dziś.

**FOUNDATION.md poprawiony 2026-07-15** (na prośbę Rafała) — sek. 3 „Zasady commitów" niesie już regułę polską, więc zapis i praktyka się zgadzają. Uwaga: FOUNDATION.md to lokalna kopia w repo (w gicie od pierwszego commita), a nie plik współdzielony — **kopie w innych projektach Rafała nadal mają starą regułę „subject po angielsku"** i tam ta poprawka nie dotarła.
