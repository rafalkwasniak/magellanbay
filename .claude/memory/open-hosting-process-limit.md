---
name: open-hosting-process-limit
description: "OTWARTE od 2026-07-15 — konto host473413 przekracza limit 250 procesów (~104 incydenty/12h); Kramio niewinne, hipoteza = crony curl bez --max-time."
metadata: 
  node_type: memory
  type: project
  originSessionId: d43b718a-bf6f-4d34-bb7d-d0b76a72a4c6
  modified: 2026-08-08T10:04:50.121Z
---

## 🚚 PLANOWANA PRZEPROWADZKA (Rafał, 2026-08-08)
*„Kramio najpewniej przejdzie na nowy serwer o tych samych parametrach."* Termin nieustalony. **Te same parametry = limit 250 procesów ZOSTAJE** — przeprowadzka niczego tu nie rozwiązuje, zasada „testy filtrowane w trakcie, pełna suita raz przed commitem" obowiązuje dalej. Przy okazji migracji: sprawdzić crontab (7 projektów, patrz niżej), wildcard DNS i SSL `*.kramio.pl` ([[multitenant-subdomain-architecture]]) oraz ścieżkę PHP 8.5 (`/opt/alt/php85`).

**OTWARTE — Rafał świadomie odłożył 2026-07-15: „nie chcę ich teraz na szybko zmieniać, rozwiążemy jak będzie więcej czasu".** Nie ruszać crontaba bez jego słowa (produkcja, cudze domeny).

Hosting (`host473413.xce.pl`, DirectAdmin) wysyła alerty: przekroczony **limit 250 procesów**, strona niedostępna **104× w 12 h**. Zaczęło się ~2026-07-13.

**POTWIERDZONE TWARDO:**
- **Kramio jest niewinne.** Jeden cron (`schedule:run`), jedyne zadanie `email:dispatch` ma `withoutOverlapping()` (`routes/console.php`). Slow-query log z 15.07 waży 825 B. Storefront bez ruchu.
- Limit dotyczy **całego konta**, nie domeny — w crontabie siedzi 7 projektów (pobierzgpx.pl, pobierzqr.pl, device.ursalogic.pl, kociaczek.com.pl, curvia.kwasniak.org, purl.pl, kramio.pl).
- **`device.ursalogic.pl queue:work --queue=low --max-time=300` odpalany co minutę = stałe 5 workerów** (widziane naraz: 00:22, 01:22, 02:22, 03:23, 04:23). Realne marnotrawstwo, ale 5/250 — NIE przyczyna alertu. Fix: `--max-time=55`. Kolejka `high` jest OK, bo `--stop-when-empty` kończy ją od razu.

**HIPOTEZA (mocna, ale NIEZWERYFIKOWANA — nie widziana w akcji):** crony `curl` bez `--max-time`, odpalane co minutę, uderzające w wolne endpointy:
```
* * * * * curl -L -s http://pobierzgpx.pl/cron/read_point_address
* * * * * curl -L -s https://pobierzgpx.pl/cron/read_deep_seek_description   ← woła DeepSeek (AI, wolne)
```
Curl domyślnie czeka bez końca. Endpoint > 60 s → curl wisi → minutę później startuje kolejny → **każdy wiszący curl trzyma proces PHP po stronie WWW** → narastanie liniowe do 250. Tłumaczy „website not available" (procesy zjadają własne crony) i „od 2 dni" bez zmian w kodzie (wystarczy, że DeepSeek zwolnił lub przybyło danych).

Proponowany fix (do przedyskutowania): `flock -n /tmp/x.lock curl -L -s --max-time 50 …` — `--max-time` ucina wiszący request przed startem następnego, `flock -n` nie pozwala drugiemu wystartować.

## ⚠️ INCYDENT 2026-08-02 18:15–18:17 — TYM RAZEM WINNA BYŁA SUITA TESTÓW

Pierwszy przypadek, w którym **przyczyna leżała po NASZEJ stronie i jest w pełni ustalona**.

Asystent puszczał pełną suitę (1200+ testów) kilkanaście razy w ciągu jednej sesji, po każdej drobnej zmianie, do tego ImageMagick przy grafikach OG. Limit procesów konta się wyczerpał i:
- crony przestały startować — `Fork failed: Resource temporarily unavailable` dla `email:dispatch` i `queue:work`, alert na Discordzie o 18:17 (`exit code 254`);
- **przestał odpowiadać sam asystent**, bo jego własne komendy też nie miały na czym wystartować. Z zewnątrz wyglądało to na zawieszenie („dawno już wisisz") — w rzeczywistości system nie mógł rozwidlić procesu;
- Rafał odblokował sytuację, ubijając procesy z terminala.

**Szkód nie było:** kolejka pusta, zero zaległych maili, zero `failed_jobs`. Cron nadrobił przy kolejnym przebiegu — przez ~2 minuty po prostu nic nie wychodziło.

**ZASADA NA PRZYSZŁOŚĆ:** w trakcie pracy uruchamiać testy **filtrowane** (`--filter=NazwaTestu`), a PEŁNĄ suitę raz — przed commitem. Puszczanie całości po każdej zmianie na koncie produkcyjnym potrafi zdusić crony CAŁEGO konta (7 projektów), nie tylko Kramio.

**Wniosek pozytywny:** alert Discorda ([[handoff-2026-07-31-security]]) zadziałał dokładnie tak, jak miał — Rafał wiedział o awarii w chwili jej wystąpienia, z nazwą polecenia i kodem wyjścia.

**How to apply:** **NAJPIERW zweryfikować w panelu** (`http://host473413.xce.pl:2222` → Resource Usage → godziny incydentów) — rozłożone równomiernie po dobie = cron, hipoteza się broni; skupione w szczytach ruchu = realny ruch/boty, szukać dalej. Nie zakładać, że przyczyna jest znana — potwierdzone jest tylko to, co wyżej. Powiązane: [[orphaned-build-processes-incident]] (ten sam limit fork() ugryzł wcześniej przy sierotach npm/vite), [[shared-hosting-constraints]], [[deepseek-ai-improve]].
