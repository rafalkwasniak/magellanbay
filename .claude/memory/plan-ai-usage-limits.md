---
name: plan-ai-usage-limits
description: "WDROŻONE 2026-07-28 (commit 0cc024b): tygodniowy limit ZADAŃ AI per pakiet (100/400/800, okno ISO). Zmierzone koszty: wywołanie to grosz, nie złotówka — limit chroni przed skryptem, nie przed sprzedawcą."
metadata: 
  node_type: memory
  type: project
  originSessionId: 37f72f3b-963d-48a4-ba3e-06ca036f5d60
  modified: 2026-07-28T08:17:54.389Z
---

**Rafał, 2026-07-27: „musimy zrobić — i to musisz zapamiętać — że pakiety będą miały limity użyć AI (…) BEZ WZGLĘDU NA TO, co robi i do czego używa. Jak mi się zarejestruje 50 sklepów darmowych, to będę do tego dopłacał, jak będą klikać jak najęci."**
Ustalenia wykonawcze zapadły 2026-07-28 po policzeniu realnych kosztów.

## WDROŻONE 2026-07-28 (commit `0cc024b`, 976 testów, przetestowane NA ŻYWO przez Rafała — symulacja Stragan/limit 5)
`ai_weekly_limit` w `config/shop.php` (100/400/800) + snapshot sklepu · tabela `ai_usages` + `App\Models\AiUsage` · `App\Services\AiQuota` (**singleton!** bez tego licznik odpytywał bazę 4× na jedno wejście w formularz produktu — złapane testem, nie okiem) · `AiQuotaExceededException` (niesie limit i datę powrotu) · egzekwowanie w `AiClient::run()`, gdzie **sklep jest OBOWIĄZKOWYM argumentem**, żeby nowe wywołanie AI nie ominęło limitu przez zapomnienie · `task_id` generowany w `resources/js/ai.js` scala fragmenty w jedno zadanie · licznik `components/seller/ai-quota.blade.php` + `window.setAiQuota()` jako **skrypt INLINE** (zmiany licznika nie wymagają przebudowy Vite) · odpowiedzi AI niosą `remaining`, więc licznik zbija się natychmiast.
Testy: `tests/Feature/Ai/{AiQuota,AiQuotaEnforcement,AiQuotaVisibility}Test.php`.
**Ton komunikatu (poprawiony po uwadze Rafała, że „wygląda walidacyjnie"):** wyczerpana pula to INFORMACJA, nie błąd — bursztynowy kafelek i dymek `info` zamiast czerwonego: „Wykorzystałeś całą pulę AI na ten tydzień — 400 użyć. Nowa czeka już w poniedziałek, 3 sierpnia. W wyższym pakiecie pula jest większa."

## ZMIERZONE KOSZTY (2026-07-28, realne treści z `ilikemybike`)
Cennik DeepSeek (api-docs.deepseek.com/quick_start/pricing): **Flash** $0,14/mln wejścia, $0,28/mln wyjścia, cache hit $0,0028 — **Pro** $0,435 / $0,87 / $0,003625. Kurs NBP 3,7835 zł/USD.

| Wywołanie | Tokeny (we/wy) | Koszt | 1000 szt. |
|---|---|---|---|
| Opis SEO | 629 / 136 | **0,05 gr** | 0,48 zł |
| Korekta 1 fragmentu | 614 / 980 | **0,14 gr** | 1,36 zł |
| Korekta długiego opisu (5 fragm.) | 3070 / 4900 | **0,7 gr** | 6,82 zł |
| Opis produktu od zera (Pro, szacunek) | 1000 / 1500 | **0,7 gr** | 6,58 zł |

**Wniosek, który zmienia sens mechanizmu:** wywołanie kosztuje GROSZ, nie złotówkę. 50 darmowych sklepów po 30 wywołań/tydzień ≈ **2 zł/tydzień** — tam nie ma czego oszczędzać. Limit chroni przed PĘTLĄ I SKRYPTEM: dzisiejszy throttle 30/min pozwala na ~43 tys. wywołań dziennie z jednego konta, czyli **~60 zł/dzień**. Drugi sens: bezkosztowy wyróżnik pakietu.

## DECYZJE RAFAŁA (2026-07-28)
1. **Jednostką jest ZADANIE, nie wywołanie.** Jeden długi tekst poprawiany w 20 fragmentach = **1 użycie**, nie 20.
2. **Każde użycie AI zmniejsza limit o 1** — bez wyjątków dla automatu (auto-opis SEO też liczy).
3. **Limity tygodniowe: Kram 100 / Stragan 400 / Pawilon 800.** Uzasadnienie Rafała: „ktoś na początku użyje więcej AI, później już sprzedaje, nie będzie klikał codziennie, jak uzupełni sklep na gotowo". Koszt przy pełnym wykorzystaniu: Pawilon ~1,1 zł/tydzień (~5 zł/mc przy abonamencie 150 zł).

## MECHANIZM (do wdrożenia)
- **Okno = numer tygodnia ISO**, klucz `2026-W31` (`date('o-\WW')`): tydzień od poniedziałku, bez rozjazdu na przełomie roku.
- **Limit jako uprawnienie pakietu**: `ai_weekly_limit` (int) w `config/shop.php` i w snapshocie sklepu — jak `max_products` ([[plan-packages]]). Konsola admina od razu pozwala podbić limit per sklep, a wygaśnięcie pakietu ([[plan-subscription-expiry]]) samo sprowadza go do darmowego.
- **Licznik**: tabela `ai_usages` (shop_id, period, calls) + unique(shop_id, period), atomowy upsert — wzorzec z `shop_stats`. Daje przy okazji dane „ile ten sklep korzysta z AI".
- **Egzekwowanie w `AiClient::run()`** — to JEDYNE miejsce rozmawiające z API. Dołożyć sklep do sygnatury, żeby nowe wywołanie nie mogło ominąć limitu przez zapomnienie.
- **„Zadanie" przy korekcie dzielonej na fragmenty** ([[plan-ai-chunked-correction]]): dzielenie robi przeglądarka (`resources/js/ai.js`), więc każdy fragment to osobne żądanie HTTP. Rozwiązanie: przy kliknięciu „Popraw przez AI" front generuje **identyfikator zadania (UUID)** i wysyła go z każdym fragmentem; serwer zlicza jednostkę tylko przy PIERWSZYM wystąpieniu danego UUID (znacznik w cache, np. 30 min). Klient, który podrzuciłby nowy UUID na fragment, zużyłby WIĘCEJ jednostek — nie da się tego wykorzystać na swoją korzyść.
- **Komunikat po wyczerpaniu** ma mówić, ile zostało, KIEDY limit wraca („w poniedziałek") i co daje wyższy pakiet — to naturalny moment na upsell, nie suchy błąd.
- Throttle 30/min zostaje jako druga zapora (limit tygodniowy nie broni przed nagłym wystrzałem w jedną minutę).

Powiązane: [[ai-task-profiles-architecture]] (kod woła ZADANIE, nie model), [[plan-ai-chunked-correction]], [[plan-packages]], [[deepseek-ai-improve]], [[plan-seo-audit]] (opisy SEO to nowy konsument limitu).
