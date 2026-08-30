---
name: plan-ai-streaming-response
description: "WDROŻONE 31.07 (`4281095`): streaming odpowiedzi AI (SSE) z efektem wymazywania — tekst pisze się na żywo; AI_STREAMING=false to wyłącznik awaryjny."
metadata: 
  node_type: memory
  type: project
  originSessionId: f28bb4c2-d597-45e3-849b-2d73e6382e24
  modified: 2026-07-31T12:21:27.980Z
---

**MODUŁ KOMPLETNY 2026-07-31** (`4281095`), przetestowany przez Rafała na żywo („ładnie to wygląda"). Historia pomysłu: zaakceptowany 26.07, odłożony; wdrożony razem z naprawą reasoning_effort (patrz [[deepseek-ai-improve]]).

**Jak działa (choreografia):**
1. Klik „Popraw przez AI" → tekst ZNIKA od końca (backspace edytora, ~2,5 s) — efekt „AI zabiera tekst do poprawy" (pomysł Rafała), który przy okazji MASKUJE fazę myślenia modelu i biegnie równolegle z żądaniami (nie kosztuje ani sekundy).
2. Poprawiona wersja pisze się w puste pole jedną falą od góry.

**Architektura:**
- `AiClient::stream()` — `stream: true` do DeepSeeka, parsowanie SSE, kawałki do callbacka, tok myślenia pomijany, limit puli jak w `run()`.
- `AiController::improveStream()` — SSE: `{"delta"}` po drodze, `{"done", text, remaining}` na końcu; BŁĘDY JADĄ W STRUMIENIU (nagłówki 200 wychodzą przed pracą modelu), w tym limit 429 z tą samą treścią co JSON.
- `ai.js` — czytanie strumienia przez `fetch` (EventSource nie umie POST); fragmenty lecą RÓWNOLEGLE, ale odsłania się zawsze najwcześniejszy nieukończony (bufory dla późniejszych). **Wersja „fragmenty po kolei" ODRZUCONA po teście Rafała**: każdy fragment płaci osobno fazę myślenia → dwa pisania przedzielone martwą ciszą i 2× czas.
- Dwie warstwy: `display` (widok po wymazaniu) i `results` (prawda — oryginały); po KAŻDEJ awarii, także totalnej, zapis końcowy odtwarza pole.
- Trix na żywo: `trimIncomplete()` ucina niedomknięty znacznik/encję z końca strumienia; odrysowanie ≤ co 250 ms.

**Wyłącznik awaryjny:** `AI_STREAMING=false` w `.env` → serwer odpowiada JSON-em, front poznaje po Content-Type i wraca do starej ścieżki bez zmian w kodzie.

**Shared host:** bramka sprawdzona 31.07 — LiteSpeed honoruje `X-Accel-Buffering: no` + `flush()` i NIE buforuje (test: 10 zdarzeń co 1 s doszło co 1 s). W streamie kontrolera bufory PHP zdejmowane tylko poza testami (`app()->runningUnitTests()` — inaczej wywala przechwytywanie PHPUnit).

Powiązane: [[ai-task-profiles-architecture]], [[deepseek-ai-improve]], [[plan-ai-chunked-correction]].
