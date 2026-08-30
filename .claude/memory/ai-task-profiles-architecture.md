---
name: ai-task-profiles-architecture
description: "AI: kod wola ZADANIE (proofread/product_copy), nie model — config/ai.php mapuje zadanie na dostawcę i model. Nigdy nie zaszywaj nazwy modelu w kodzie."
metadata: 
  node_type: memory
  type: project
  originSessionId: 3555802d-d4fb-4f61-936d-2ec6d0355b27
  modified: 2026-07-26T06:16:29.607Z
---

USTALONE 2026-07-26 (Rafał: „nie odwołujemy się do konkretnego modelu, a do tego, co ma robić"). Wdrożone i pokryte testami (`tests/Feature/AiProfileTest.php`).

**Zasada:** żadna nazwa modelu ani adres dostawcy nie może pojawić się w kodzie aplikacji — wyłącznie w `config/ai.php`. Kod woła zadanie: `$ai->run('proofread', $system, $tresc)`.

Trzy warstwy w `config/ai.php`:
- `providers` — konto u dostawcy (`base_url` + `key` z `.env`). Dopisanie kolejnego dostawcy = wpis w tablicy, zero zmian w kodzie (wszyscy mówią dialektem OpenAI: `POST /chat/completions`).
- `defaults` — baza dziedziczona przez każde zadanie (provider, model, `reasoning_effort`, `temperature`, `timeout`).
- `tasks` — zadanie nadpisuje **wybiórczo** tylko to, co je odróżnia; reszta spada na `defaults`.

Kod:
- `App\Services\Ai\AiProfile::forTask($task)` — scala defaults + nadpisania + dane dostawcy w jeden obiekt. Nieznane zadanie lub dostawca = `RuntimeException` od razu (świadomie: literówka w nazwie zadania nie może po cichu zjechać na inny model).
- `App\Services\Ai\AiClient::run($task, $system, $tresc)` — JEDYNE miejsce rozmawiające z API modelu. Czyta `choices.0.message.content` (tok rozumowania modele zwracają osobno w `reasoning_content`).
- `App\Services\AiTextImprover` — już tylko warstwa promptów, deleguje do `AiClient` ze stałą `TASK = 'proofread'`.

Zadania dziś: `proofread` (redakcja tego, co sprzedawca napisał — dziedziczy wszystko, czyli tanio) i `product_copy` (tworzenie opisu od zera — `deepseek-v4-pro`, effort `medium`, temperature 0.8, timeout 180 s). **`product_copy` istnieje, ale nie jest jeszcze przez nic wołane** — profil czeka gotowy na funkcję generowania opisów.

`.env`: klucz per dostawca (`DEEPSEEK_API_KEY`, `DEEPSEEK_BASE_URL`), ustawienia domyślne jako `AI_PROVIDER` / `AI_MODEL` / `AI_REASONING_EFFORT` / `AI_TIMEOUT`. Model konkretnego zadania nadpisuje się w `config/ai.php`, NIE w `.env`. Stary blok `config('services.deepseek.*')` już nie istnieje.

**Do zrobienia, gdy powstanie generowanie opisów:** to inna klasa kosztu niż redakcja (mocniejszy model + pisanie od zera, a sprzedawca klika, aż mu się spodoba). Od startu traktować jako funkcję pakietową — limit wywołań albo bramka Stragan+, nie doklejać bramki później. Powiązane: [[plan-packages]], [[deepseek-ai-improve]].
