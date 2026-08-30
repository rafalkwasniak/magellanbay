---
name: disabled-ursalogic-queue-crons
description: "PRZYWRÓCONE 2026-08-08 na słowo Rafała — 2 crony queue:work device.ursalogic.pl znów działają. Zostaje jedna znana nieoptymalność: wpis `low` ma --max-time=300 przy odpaleniu co minutę, więc workery się nakładają."
metadata: 
  node_type: memory
  type: project
  originSessionId: e03d2a67-a20c-47fc-82f6-de09ee8ca64e
  modified: 2026-08-12T17:47:07.844Z
---

## ✅ PRZYWRÓCONE 2026-08-08

Rafał: *„Ursalogic musiałaby zostać uruchomiona, bo już wiemy czemu były problemy."* Zdjęty prefiks `# WYLACZONE …` z obu wpisów, zweryfikowane `crontab -l` **oraz** realnym `ps` (workery wystartowały w tej samej minucie). Backup crontaba sprzed zmiany: `/home/host473413/crontab-backup-2026-08-08.txt` (starszy, sprzed wyłączenia: `/home/host473413/crontab-backup-2026-07-26.txt`).

Wpisy w obecnej postaci, oba `* * * * *`:

```
* * * * * /home/host473413/domains/device.ursalogic.pl/artisan queue:work --queue=high --timeout=300 --sleep=1 --stop-when-empty
* * * * * /home/host473413/domains/device.ursalogic.pl/artisan queue:work --queue=low --timeout=150 --sleep=2 --max-time=300
```

## 🛑 DECYZJA 2026-08-12: WSTRZYMANE do nowego serwera

Rafał: *„ursalogic to projekt, który musi działać, więc jeśli na teraz nie ma problemów z wydajnością, ja bym to zostawił. Jak nie ma alertów, wstrzymałbym sprawę aż do postawienia na nowym serwerze."*

Pomiar z tego dnia potwierdza, że to nie pali się: **29 procesów konta na 250** (12% limitu), z czego **7 to nakładające się workery `low`**. Mechanizm istnieje, koszt jest znikomy.

**Warunek odwrócenia decyzji:** wrócą alerty o limicie procesów ([[open-hosting-process-limit]]) albo coś na koncie zacznie się dusić. Wtedy `--max-time=55` jest pierwszym ruchem, bo najtańszym. Poza tym przypadkiem — **nie ruszać cudzego działającego projektu**; na nowym serwerze crony i tak powstaną od zera ([[plan-dev-environment]]).

## ⚠️ NIEOPTYMALNOŚĆ (opisana, świadomie NIE naprawiana) — nakładające się workery `low`

Wpis `low` ma `--max-time=300` przy odpaleniu **co minutę**, więc pięć instancji żyje jednocześnie (zmierzone 15.07: workery z 00:22, 01:22, 02:22, 03:23, 04:23 naraz). Kolejka `high` jest OK — `--stop-when-empty` kończy ją od razu.

**Fix to jedna liczba: `--max-time=55`.** Świadomie NIE zastosowany przy przywracaniu — Rafał poprosił o uruchomienie, nie o zmianę parametrów, a to cudzy projekt na produkcji. Do zaproponowania przy okazji.

Skala: 5 procesów przy limicie 250 — realne marnotrawstwo, ale **nie przyczyna alertów** ([[open-hosting-process-limit]]).

## Dlaczego były wyłączone (26.07 → 08.08)
AI w ursalogic przestało działać (ta sama przyczyna co w Kramio — DeepSeek wycofał `deepseek-chat`, [[deepseek-ai-improve]]), więc workery mieliły w kółko zadania, które i tak padały. Naprawione 31.07: model `deepseek-v4-flash`, `reasoning_effort=low`, wycięte `max_tokens`.

**UCZCIWIE, żeby nie odtwarzać fałszywej teorii:** to NIE ursalogic blokował build Kramio. Pomiar z tego samego wieczoru: 212 wątków na koncie, z czego **195 zjadał VS Code Server**. Patrz [[vite-build-rayon-threads]].

Gotcha: `crontab <plik>` przy pierwszym podejściu potrafi zwrócić rc=0 bez zapisania zmiany — **ZAWSZE** weryfikować `crontab -l | grep <wzorzec>` po zapisie.
