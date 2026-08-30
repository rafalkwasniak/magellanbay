#!/usr/bin/env bash
#
# Pamięć asystenta: kopia w repozytorium <-> katalog roboczy w $HOME.
#
# PO CO TO JEST
# Pamięć Claude'a (handoffy, gotchy, decyzje) mieszka w katalogu domowym konta,
# czyli POZA projektem — git jej nie widzi i przy przeprowadzce na nowy serwer
# zwyczajnie by nie pojechała. Ten skrypt trzyma jej kopię w repozytorium, więc
# jedzie razem z kodem: `git clone` albo przeniesienie katalogu przez admina
# wystarczy, żeby na nowym serwerze dało się ją odtworzyć jedną komendą.
#
# UŻYCIE
#   .claude/memory-sync.sh status    # co gdzie leży i czy się różni
#   .claude/memory-sync.sh save      # $HOME -> repozytorium (przed commitem)
#   .claude/memory-sync.sh restore   # repozytorium -> $HOME (po przeprowadzce)
#
# NAZWA KATALOGU W $HOME to ścieżka bezwzględna projektu z ukośnikami i kropkami
# zamienionymi na myślniki:
#   /home/host473413/domains/kramio.pl -> -home-host473413-domains-kramio-pl
# Dlatego po przeprowadzce na konto o innej nazwie kopiowanie katalogu 1:1 NIC
# NIE DA — klucz się zmienia. Skrypt liczy go z bieżącej ścieżki, więc robi to
# poprawnie także na nowym serwerze.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_MEMORY="$REPO_ROOT/.claude/memory"

PROJECT_KEY="$(printf '%s' "$REPO_ROOT" | sed 's/[\/.]/-/g')"
HOME_MEMORY="${HOME}/.claude/projects/${PROJECT_KEY}/memory"

# Zabezpieczenie przed czyszczeniem czegokolwiek spoza własnego katalogu kopii.
assert_repo_memory_path() {
    case "$REPO_MEMORY" in
        */.claude/memory) ;;
        *) echo "PRZERWANE: nieoczekiwana ścieżka kopii: $REPO_MEMORY" >&2; exit 1 ;;
    esac
}

count_files() {
    [ -d "$1" ] && find "$1" -maxdepth 1 -name '*.md' | wc -l | tr -d ' ' || echo 0
}

cmd_status() {
    echo "Projekt:       $REPO_ROOT"
    echo "Klucz:         $PROJECT_KEY"
    echo
    echo "Kopia w repo:  $REPO_MEMORY"
    echo "               $(count_files "$REPO_MEMORY") plików .md"
    echo "Robocza:       $HOME_MEMORY"
    echo "               $(count_files "$HOME_MEMORY") plików .md$([ -d "$HOME_MEMORY" ] || echo '  (KATALOG NIE ISTNIEJE)')"

    if [ -d "$REPO_MEMORY" ] && [ -d "$HOME_MEMORY" ]; then
        echo
        if diff -rq "$REPO_MEMORY" "$HOME_MEMORY" >/dev/null 2>&1; then
            echo "Zgodne."
        else
            echo "RÓŻNICE (kopia w repo vs robocza):"
            diff -rq "$REPO_MEMORY" "$HOME_MEMORY" 2>&1 | sed 's/^/  /'
        fi
    fi
}

cmd_save() {
    assert_repo_memory_path
    if [ ! -d "$HOME_MEMORY" ]; then
        echo "PRZERWANE: brak pamięci roboczej w $HOME_MEMORY — nie ma czego zapisać." >&2
        exit 1
    fi

    rm -rf "${REPO_MEMORY:?}"
    mkdir -p "$REPO_MEMORY"
    cp -a "$HOME_MEMORY/." "$REPO_MEMORY/"

    echo "Zapisano $(count_files "$REPO_MEMORY") plików do $REPO_MEMORY"
    echo "Pamiętaj o commicie — bez niego kopia nie pojedzie z repozytorium."
}

cmd_restore() {
    if [ ! -d "$REPO_MEMORY" ]; then
        echo "PRZERWANE: brak kopii w repozytorium ($REPO_MEMORY)." >&2
        exit 1
    fi

    # Istniejącej pamięci nie nadpisujemy po cichu — na nowym serwerze może już
    # być świeższa niż kopia z repozytorium.
    if [ -d "$HOME_MEMORY" ] && [ "$(count_files "$HOME_MEMORY")" != "0" ]; then
        BACKUP="${HOME_MEMORY}.bak-$(date +%Y%m%d-%H%M%S)"
        mv "$HOME_MEMORY" "$BACKUP"
        echo "Dotychczasowa pamięć odłożona do: $BACKUP"
    fi

    mkdir -p "$HOME_MEMORY"
    cp -a "$REPO_MEMORY/." "$HOME_MEMORY/"

    echo "Odtworzono $(count_files "$HOME_MEMORY") plików do $HOME_MEMORY"
    echo "Sprawdzenie: poproś asystenta o przeczytanie MEMORY.md."
}

case "${1:-status}" in
    status)  cmd_status ;;
    save)    cmd_save ;;
    restore) cmd_restore ;;
    *)
        echo "Użycie: $0 [status|save|restore]" >&2
        exit 1
        ;;
esac
