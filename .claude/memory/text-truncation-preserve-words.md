---
name: text-truncation-preserve-words
description: "Konwencja: skracanie widocznej prozy zawsze po pełnym słowie — Str::limit(..., preserveWords: true); nigdy w połowie wyrazu."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: ce47287e-b250-4f01-a7c4-0cb2f64494a3
---

Decyzja Rafała (2026-07-11): **każde skracanie widocznego tekstu (prozy) ma kończyć się na PEŁNYM słowie, nigdy w połowie wyrazu.**

**How to apply:** używaj wbudowanego Laravela — `Str::limit($t, N, preserveWords: true)` lub fluent `Str::of($t)->limit(N, preserveWords: true)`. `preserveWords` skraca **wstecz** do ostatniego pełnego słowa (wynik ≤ N), więc nigdy nie urywa wyrazu. (Uwaga: skraca, nie wydłuża — Rafał ilustrował „210 zamiast 200", ale idiomatyczny helper cofa do słowa, co realizuje cel „nie w połowie wyrazu".)

**Why:** urwane w połowie słowo wygląda niechlujnie na froncie (opisy produktów, „O sklepie", wszelkie wycinki).

Zastosowane: `resources/views/storefront/home.blade.php` (wycinek opisu produktu w boxie 1-produktu; wycinek „O sklepie"). 

**NIE dotyczy:** `->limit()` na zapytaniach Eloquent/DB (to SQL LIMIT, nie tekst) ani twardych limitów pól API (np. `DiscordErrorReporter` — embed Discorda na komunikat/stack trace; cięcie po słowie tam bez sensu). Tam zostaje zwykły limit.

Powiązane: [[plan-storefront-editorial-and-pages]], [[form-client-validation-convention]].
