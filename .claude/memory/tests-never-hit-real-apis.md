---
name: tests-never-hit-real-apis
description: "INCYDENT 2026-07-30: suita testów wystawiła ~46 REALNYCH faktur w Fakturowni. Http::fake() z tablicą wzorców przepuszcza niedopasowane żądania NA ŻYWO. Naprawa: Http::preventStrayRequests() w TestCase + StrayGuardTest."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 97c7f155-ba79-478d-99e9-6f752187fda0
  modified: 2026-07-30T08:33:35.650Z
---

**INCYDENT 2026-07-30.** Uruchomienia suity wystawiły **~46 realnych faktur** na koncie Fakturowni Kramio (`redpaprika.fakturownia.pl`). Rafał: *„ksef wyłączyłem rano — i dobrze, bo testami zrobiłeś mi z 30 FV"*. Gdyby KSeF był włączony, poszłyby jako **realne dokumenty do urzędu**.

## Przyczyna (mechanizm, nie nieuwaga)
`PackagePaymentTest` fake'ował tylko endpoint płatności Paynow. Webhook `CONFIRMED` wołał `apply()`, ten dispatchował job faktury, a **`Http::fake(['wzorzec' => ...])` PRZEPUSZCZA wszystko, czego wzorzec nie obejmuje** — żądanie do Fakturowni poszło do internetu. Log potwierdził `testing.INFO`, seria 09:57:33–34.

Pułapka jest strukturalna: test fake'uje integrację, o której MYŚLI, a kod woła drugą, o której autor testu nie pamięta. Rośnie z każdą nową integracją i z każdym jobem odpalanym w łańcuchu.

## Naprawa
**`Http::preventStrayRequests()` w `tests/TestCase.php`** (obok gardy sqlite-only). Odwraca domyślne zachowanie: niezafake'owane żądanie **rzuca wyjątek** zamiast lecieć w świat. Chroni Fakturownię, Paynow, DeepSeek (płatne tokeny!), GUS i Discord naraz.

Plus **`tests/Feature/StrayGuardTest.php`** — dwa testy sprawdzające, że garda działa, w tym dokładnie ten układ, który zawiódł (fake na jeden endpoint, wywołanie drugiego). Bez nich wierzylibyśmy w ochronę, której nie ma.

## Reguły na przyszłość
- **Nigdy nie usuwać `preventStrayRequests()` z TestCase.** Jeśli test pada z `StrayRequestException`, to test jest niekompletny — dołóż fake, nie zdejmuj gardy.
- Testując przepływ, który odpala JOB, pamiętaj, że job woła kolejne integracje. Fake'uj cały łańcuch albo użyj `Queue::fake()`.
- **Fakturownia nie ma sandboxa.** Każde żądanie tworzy prawdziwy dokument. Przy pracy nad FV: `tries = 1`, garda idempotencji i podwójna ostrożność.
- Testy piszą do TEGO SAMEGO `storage/logs` co produkcja — wpisy `testing.INFO` obok `production.INFO`. To bywa pomocne (tak namierzyłem incydent), ale nie myl ich przy diagnozie.

Powiązane: [[plan-package-payments]] (FV za pakiet), [[deepseek-ai-improve]] (płatne wywołania), [[defer-destructive-db-guard]] (ta sama filozofia: garda w TestCase chroni produkcję).
