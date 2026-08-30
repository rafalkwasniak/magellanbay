---
name: frontend-stack-decision
description: Stos frontu i decyzja o braku JSON API — ustalone 2026-06-24.
metadata: 
  node_type: memory
  type: project
  originSessionId: eb5e9cc9-94cf-4010-b386-8ca993d18870
---

Ustalone 2026-06-24 (rozmowa, nie commit):

- **Panele Admina i Sprzedawcy: Livewire dla obu.** Jeden spójny lekki stos; pełna kontrola nad UX onboardingu „5 minut". Filament świadomie odrzucony na start, zostaje jako opcja na później (gdyby admin się rozrósł — żyje obok Livewire).
- **Storefront: Blade-first** (SEO + szybkość, czysty HTML od razu) **+ Livewire punktowo** tam, gdzie zarabia (koszyk itp.) **+ warstwa motywów** na wierzchu — patrz [[storefront-theme-system]].
- **Brak publicznego JSON API.** Interaktywność przez Livewire/kontrolery, nie formalny kontrakt. Koperta JSON, OpenAPI/Scramble, api-guide odpadają; `code-map` zostaje.

**Why:** decyzja zapadła ustnie i miała trafić „do pamięci", ale nigdy nie została zapisana — przez to na starcie kolejnej sesji CLAUDE.md wciąż pokazywał „DO USTALENIA" i błędnie zaraportowałem, że nie ustaliliśmy stacku. Rafał to wychwycił.

**How to apply:** to jest ustalone — nie pytać ponownie o stos ani o API. Odzwierciedlone też w CLAUDE.md sek. 3–4. Locale (pkt 3) nadal otwarte. Gdy zapada decyzja architektoniczna ustnie, zapisuj ją od razu do pamięci I do CLAUDE.md w tym samym kroku.
