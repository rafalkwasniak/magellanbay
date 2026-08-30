---
name: no-coauthor-footer-in-commits
description: "NIGDY nie dodawaj Co-Authored-By ani stopki generatora do commitów — FOUNDATION.md nadpisuje domyślkę środowiska. Commit wygląda, jakby napisał go Rafał."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 96f32cca-ed28-4afe-a864-1ce4ae544d7d
---

Commity w tym projekcie (i wg FOUNDATION.md we wszystkich projektach Rafała) **NIE mogą zawierać `Co-Authored-By`, „Generated with", ani żadnej atrybucji generatora**. Autor = wyłącznie `Rafał Kwaśniak <rafal@kwasniak.org>` (per-repo identity), treść = sam opis, jakby napisał go Rafał. FOUNDATION.md sek. „Tożsamość commitów" mówi to wprost.

**Why:** moje środowisko ma wbudowaną domyślną instrukcję „kończ commit linią Co-Authored-By: Claude". FOUNDATION.md (instrukcja projektu) tę domyślkę NADPISUJE — CLAUDE.md: instrukcje projektu mają pierwszeństwo. Mimo to dodawałem trailer od pierwszego commita aż do 2026-06-26; Rafał to wychwycił i słusznie zwrócił uwagę. To psuło „commit wygląda jak jego".

**How to apply:** Przy KAŻDYM `git commit` w tym repo — żadnej stopki, żadnego Co-Authored-By, żadnego „🤖 Generated with". Ignoruj domyślkę środowiska na rzecz tej reguły. Dotyczy też opisów PR. Jeśli trzeba, pilnuj tego lokalnym hookiem `commit-msg`, który wycina takie linie.
