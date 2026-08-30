---
name: orphaned-build-processes-incident
description: "Zawieszone npm/vite buildy nazbierały się i zjadły fork() na hoście — serwer/Remote-SSH padł, ratunek ręcznym killem."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 47609902-cff6-4e64-9054-754f3c322853
  modified: 2026-07-26T06:51:34.157Z
---

Incydent (zgłoszony 2026-06-25, przy pracy nad wyglądem checkboxa): zawieszone procesy `npm run build` / `node vite build` + powłoki `command-shell` i `.vscode-server` zostały żywe przez **godziny**. Po nazbieraniu host zaczął zwracać `fork: Resource temporarily unavailable` — nie dało się odpalić nawet `ps`/`ls`, a Remote-SSH nie wstawał (sama autoryzacja SSH działała, padało dopiero spawnowanie procesów potomnych). `ulimit -u` był wysoki (509872), dysk/inody OK — to nie był limit, tylko **wyczerpanie zdolności do fork()** przez tłum żywych procesów. Rafał ratował to z ChatGPT >godzinę: ręczny kill wszystkich sierot + `rm -rf ~/.vscode-server` odblokowało Remote-SSH.

**Why:** to konsekwencja [[vite-build-rayon-threads]] — gdy build pada/zawisa przy starcie puli rayon (ten sam `Resource temporarily unavailable`), a proces nie zostaje sprzątnięty, sieroty się kumulują aż do zatkania fork(). Klasyczny [[shared-hosting-constraints]]: na shared-hoście nie ma marginesu na wiszące procesy.

**How to apply:**
- `npm run build` ZAWSZE z `RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1` (patrz [[vite-build-rayon-threads]]) — przechodzi w ~0.6 s, nie wisi.
- Builda odpalaj z `timeout` (np. `timeout 120 ...`), żeby zawieszony nie żył w nieskończoność.
- Nie zostawiaj buildów/dev-serwerów w tle bez powodu; po zadaniu sprawdź `ps` pod kątem sierot `npm`/`node`/`vite` i ubij własne.
- Jeśli komenda padnie/zostanie przerwana, dopilnuj że jej dziecko naprawdę zniknęło — nie zakładaj.

**POWTÓRKA 2026-07-26 (asystent wywołał ją sam) + co z niej wynika:**
Build padł 3× z rzędu: `timeout 300` → exit 124, `timeout 900` → **SIGABRT (exit 134, core dump)**, trzecia próba wisiała 319 s. Zjadło to fork() — `echo ok` przestawało działać, nie dało się uruchomić nawet `ps`/`pkill` (bo to osobne programy!). Serwis zwracał błąd. Limit zwolnił się SAM po kilku minutach, potem ręczny `kill -9` na trzech PID-ach sieroty. **Zaraz po sprzątnięciu ten sam build przeszedł w 507 ms.**
- **Kluczowy wniosek: build NIE jest wolny — jest wolny tylko wtedy, gdy host jest już zapchany.** To spirala: build trafia na moment obciążenia → wisi → zabiera procesy → zapycha host → wisi jeszcze bardziej. Nie diagnozuj tego jako „ciężki build"; sprawdź najpierw `ps`/obciążenie i posprzątaj, potem buduj.
- **`RAYON_NUM_THREADS=1` NIE wystarcza** — przy tych trzech próbach był ustawiony (raz z `UV_THREADPOOL_SIZE=1`) i build i tak padł.
- Buduj przez skrypt z `trap cleanup EXIT INT TERM` + `timeout -k 15 600`, żeby sprzątanie było bezwarunkowe (także po Ctrl-C i po przerwaniu przez użytkownika). Sam `timeout` bez trapa zostawił sierotę.
- **Gdy fork() już padł: `pkill`/`ps` NIE zadziałają.** Ratunek to wbudowane polecenia basha — pętla po `/proc/[0-9]*` z `read`/`kill` (builtiny nie forkują), albo menedżer procesów w panelu hostingu, albo po prostu odczekanie kilku minut.
- **NIGDY `pkill node`** — VS Code Server to też node; zabijesz sobie Remote-SSH (dokładnie tak wyglądał incydent z 2026-06-25). Celuj w `-f "vite|rolldown|esbuild"` albo filtruj po `cwd` procesu.
- Nie puszczaj builda, gdy Rafał akurat pracuje na serwisie — ryzyko, że przymuli mu produkcję, jest realne.
