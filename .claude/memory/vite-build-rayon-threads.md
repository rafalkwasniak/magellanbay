---
name: vite-build-rayon-threads
description: "Build assetów (Vite 8/Rolldown) na tym hoście wymaga RAYON_NUM_THREADS=1, inaczej panic."
metadata: 
  node_type: memory
  type: project
  originSessionId: eb5e9cc9-94cf-4010-b386-8ca993d18870
  modified: 2026-07-26T21:00:29.508Z
---

`npm run build` na tym shared-hoście wywala się panikiem Rolldown: „global thread pool has not been initialized ... Resource temporarily unavailable (WouldBlock)". Vite 8 buduje przez Rolldown (Rust/rayon) i domyślna pula wątków nie startuje.

**Rozwiązanie:** budować z ograniczoną pulą wątków:

```bash
RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1 npm run build
```

Tak przechodzi w ~0.6 s. (Mimo że `ulimit -u` jest wysoki i `nproc`=32 — i tak rayon dostaje EAGAIN przy tworzeniu globalnej puli.)

**Why:** klasyczny [[shared-hosting-constraints]] — środowisko blokuje coś nieoczywistego, lekka korekta odblokowuje.

**How to apply:** zawsze dawaj `RAYON_NUM_THREADS=1` przed buildem w tym projekcie. Jeśli kiedyś przejdzie bez tego (zmiana hosta/wersji), można odpuścić.

**WAŻNE (2026-06-26):** sam `npm run build` potrafi zawisnąć NA AMEN (timeout, nawet z `RAYON_NUM_THREADS=1`, bez paniki — po prostu wisi), podczas gdy **`./node_modules/.bin/vite build` wprost przechodzi w ~150 ms**. To wrapper `npm` jest niestabilny na tym hoście, nie sam Vite. Gdy build wisi: zabij, uruchom `RAYON_NUM_THREADS=1 ./node_modules/.bin/vite build` bezpośrednio (foreground + `timeout`). Działa też `--logLevel info`, by zobaczyć, gdzie staje.

**INTERMITTENTNIE (2026-06-26 wieczór):** build zaczął padać na `ERR_WORKER_INIT_FAILED` w `@tailwindcss/node` (Tailwind v4 oxide próbuje odpalić worker thread, host odmawia). To **limit na poziomie hosta (LVE) na wątki/procesy**, nie liczba procesów — zabicie helperów VSCode (tsserver itd.) NIE pomaga, bo odradzają się natychmiast. Build działał wcześniej tego samego dnia, więc to zależne od chwilowego obciążenia. **Rafał (2026-06-26): „jutro sprawdzimy co zżera procesy i coś z tym zrobimy".** TODO: zdiagnozować zużycie procesów/wątków usera (ile, co trzyma) i podnieść/odblokować limit LVE albo odchudzić.

**POWTÓRKA (2026-06-28):** build padał wyjątkowo często — panika Rolldown ORAZ zawisanie (timeout), mimo `RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1`, pod obciążeniem hosta (load ~6). Raz wyszło `fork: Resource temporarily unavailable` (znów limit LVE). Co dowoziło: **łagodna SEKWENCYJNA pętla ponawiająca** (jedna próba naraz, `timeout 90`, `sleep 15-20` między, do ~8 prób) w tle — przechodziła zwykle za 4–6 podejściem. NIE odpalać pętli równolegle z testami (dokłada procesów → fork-exhaustion). UWAGA: cały czas używałem `npm run build`; następnym razem najpierw spróbować `RAYON_NUM_THREADS=1 ./node_modules/.bin/vite build` wprost (patrz wyżej — wrapper npm jest podejrzany). **POTWIERDZONE tego samego dnia:** po serii padów `npm run build`, `RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1 ./node_modules/.bin/vite build` przeszło OD RAZU (exit 0, ~300 ms). Wniosek: zaczynaj od direct vite, `npm run build` zostaw jako ostateczność.

**POTWIERDZONE (2026-06-29):** pod load ~7 i presją wątków od tsserver VS Code `npm run build` padał na EAGAIN (rayon „global thread pool has not been initialized") kilka razy z rzędu mimo `RAYON_NUM_THREADS=1`. Co dowiozło OD RAZU: dorzucenie **`TOKIO_WORKER_THREADS=1`** do zestawu — pełna komenda `RAYON_NUM_THREADS=1 TOKIO_WORKER_THREADS=1 UV_THREADPOOL_SIZE=1 npm run build` (~215 ms). Rolldown używa tokio, więc ograniczenie i jego puli domyka temat. Kolejność prób na przyszłość: (1) direct `RAYON_NUM_THREADS=1 ./node_modules/.bin/vite build`; (2) dorzuć `TOKIO_WORKER_THREADS=1 UV_THREADPOOL_SIZE=1`.

**Most awaryjny gdy build nie idzie, a trzeba dorzucić CZYSTY CSS** (reguły, nie utility): dopisz reguły wprost na koniec aktualnego `public/build/assets/app-*.css` (sprawdź, że używane `var(--color-*)` już tam są). Źródło `app.css` i tak miej zaktualizowane — następny udany build wygeneruje nowy hash i przejmie to czysto. Tak zrobiono `.legal-content` 2026-06-26.

**Uwaga:** gdy build pada/zawisa na tym panicu i proces nie zostaje sprzątnięty, sieroty `npm`/`node`/`vite` kumulują się i zatykają `fork()` całego hosta — patrz incydent [[orphaned-build-processes-incident]]. Stąd: build z `RAYON_NUM_THREADS=1` + `timeout`, i sprzątaj po sobie.

**FONTY SELF-HOSTED (2026-07-11) — usunięto NOWE źródło zawisania:** `laravel-vite-plugin/fonts` `bunny()` pobiera fonty z fonts.bunny.net W CZASIE BUILDA. Gdy Bunny odpowiada wolno/blokuje (po serii powtórek — flakiness [[shared-hosting-constraints]]), **build wisi na fetchu** (nie panic, po prostu timeout) — mylące, bo wygląda jak rayon. Rozwiązanie trwałe: **wyrzucony plugin `bunny()` z `vite.config.js`; oba fonty (Instrument Sans 400/500/600 + Instrument Serif 400, subsety latin+latin-ext, woff2) leżą w `public/fonts/` i ładowane własnym `@font-face` w `resources/css/app.css` (absolutny URL `/fonts/…`, Vite ich nie przetwarza).** Build jest teraz OFFLINE (~380 ms, zero sieci). Pliki woff2 w repo (commit). Gdy trzeba dodać font: `curl` z Bunny CSS (`https://fonts.bunny.net/css?family=<font>:<wagi>`) po URL-e woff2, pobrać do `public/fonts`, dopisać `@font-face`. `--font-serif` w `@theme` → klasa Tailwinda `font-serif` (JIT: wygeneruje się dopiero gdy użyta w szablonie + rebuild).

**POTWIERDZONE PO RAZ KOLEJNY (2026-07-26), bo asystent znów budował przez npm:** `npm run build` wisiał 2× (600 s i 60 s timeout — nawet nie wypisał wersji vite, czyli wisi SAM npm na starcie, przy load ~8), a host mulił Rafałowi na kilka minut. `RAYON_NUM_THREADS=1 timeout -k 10 60 node node_modules/vite/bin/vite.js build` → **363 ms, od pierwszego strzału**. ZASADA OSTATECZNA: w tym projekcie **NIGDY nie buduj przez `npm run build` — ZAWSZE `node node_modules/vite/bin/vite.js build`** (z RAYON_NUM_THREADS=1 + timeout + pkill po sobie). Czerwona lampka: jeśli po 5 s nie ma linii „vite v…", to już wisi — zabij, nie czekaj na timeout, bo wiszący build dławi cały serwis w ramach LVE.

**ZMIERZONE ŹRÓDŁO PROBLEMU (2026-07-26 wieczór) — najkonkretniejsza diagnoza, jaką mamy:**
Po serii ~15 nieudanych buildów (raz wisi, raz panic, raz SIGABRT) policzyłem wątki konta:
- **212 wątków w 37 procesach, z czego VS Code Server zjadał 195 (92 %)** — tsserver ×2 (po ~231 MB), extension hosty, file watchery.
- load 5.8–9 przy **32 rdzeniach** = serwer wcale nie był przeciążony (~20 %); `ulimit -u` = 509 872, więc limit procesów też NIE był problemem.
- workery `queue:work` z ursalogic to ~4 procesy po JEDNYM wątku — nieistotne dla builda (choć wyłączone z innego powodu, patrz [[disabled-ursalogic-queue-crons]]).

**Wniosek: hamulcem jest pula wątków zjadana przez VS Code Server, nie obciążenie hosta ani nasz kod.** Rolldown przy starcie chce swoją pulę i dostaje EAGAIN.

**CO DOWIOZŁO (Rafał, po zamknięciu VS Code, z czystego SSH):** `RAYON_NUM_THREADS=1 node node_modules/vite/bin/vite.js build` → pierwszy strzał panic, **drugi przeszedł w 613 ms, trzeci w 306 ms**. Czyli: zamknięcie VS Code + 1–2 powtórki = pewny build.
**PROCEDURA, gdy build nie idzie mimo direct vite:** poproś Rafała, żeby zamknął VS Code i uruchomił build z czystego terminala SSH. Asystent siedzi WEWNĄTRZ VS Code, więc sam tych 195 wątków nie zwolni — to jedyna rzecz, której nie zrobi za użytkownika.

**PUŁAPKA PRZY WERYFIKACJI (nie powtórzyć):** build minifikuje, więc `grep <nazwaFunkcji>` w `public/build/assets/*.js` NIC nie znajdzie — nazwy lokalne są przemianowane. Dałem Rafałowi taki test i wyglądało, że udany build się nie wdrożył. **Sprawdzaj po STRINGACH i literałach**, które minifikator zachowuje (np. `·`, `<br>`, `innerHTML`, teksty UI), albo po dacie/rozmiarze pliku i wpisie w `manifest.json`.

**KLUCZOWY WNIOSEK o LVE (2026-07-11):** gdy buildy wiszą seryjnie, prawdziwym hamulcem bywa **nawał wiszących procesów przebijający limit LVE** — nowe procesy są wtedy dławione / dostają SIGABRT (widoczne też u usera jako „Claude Code terminated by signal SIGABRT"). Lekarstwo: **ubić WSZYSTKIE** `node/vite/rolldown` (pkill + `kill -9` po PID, sprawdzić `ps` że 0), i dopiero na CZYSTYM slocie jeden build — wtedy przechodzi od razu. Nie ponawiać w kółko przy żywych sierotach (dokłada do limitu). Live site cały czas leci na ostatnim dobrym `public/build` — buildy go nie ruszają, dopóki nowy nie zapisze się w całości.
