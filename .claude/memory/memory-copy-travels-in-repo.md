---
name: memory-copy-travels-in-repo
description: "Pamięć asystenta ma kopię w repo (.claude/memory) — przy CP z nowym handoffem najpierw `.claude/memory-sync.sh save`, potem commit."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 03abb980-bdf6-4baf-add2-bff53c47b32c
  modified: 2026-08-30T20:31:31.551Z
---

Od 2026-08-30 repozytorium wozi **kopię tej pamięci** w `.claude/memory/`, a `.claude/memory-sync.sh` przenosi ją w obie strony (`status` / `save` / `restore`). Instrukcja przeprowadzki to sek. 6 `CLAUDE.md`.

**Why:** pamięć mieszka w `~/.claude/projects/<klucz>/memory/`, czyli poza projektem — git jej nie widzi. Rafał szykuje przeprowadzkę na nowy serwer ([[plan-dev-environment]]) i sam zauważył, że skoro admin przeniesie cały katalog projektu, to kopia w projekcie pojedzie razem z kodem i nikt nie musi pamiętać o ukrytym katalogu w home. Pomysł był jego i jest lepszy od mojego („skopiuj ręcznie ten katalog").

**How to apply:**
- Przy każdym „CP" po sesji, w której powstał handoff albo nowa notatka: **najpierw `.claude/memory-sync.sh save`, potem commit**. Kopia starzeje się cicho i nikt jej nie sprawdzi za mnie — nieaktualna jest niewiele lepsza od żadnej.
- Na nowym serwerze: `.claude/memory-sync.sh restore`, potem przeczytać `MEMORY.md`.
- **GOTCHA:** nazwa katalogu w `$HOME` to ścieżka bezwzględna projektu z `/` i `.` zamienionymi na `-` (`/home/host473413/domains/kramio.pl` → `-home-host473413-domains-kramio-pl`). Po zmianie konta skopiowanie katalogu 1:1 NIC NIE DA — klucz się zmienia. Skrypt liczy go z `pwd`, więc trafia sam.
- W pamięci nie ma sekretów (sprawdzone 30.08: tylko NAZWY kluczy `.env`, te same co w `.env.example`) — ale przed każdym `save` warto o tym pamiętać, bo to jedyna rzecz, która mogłaby tu wjechać do repozytorium przez przypadek.

Powiązane: [[plan-dev-environment]], [[migration-to-kramio]], [[cp-shorthand-commit-push]].
