# Memory index

- [PRIORYTETY: najpierw wyjście do ludzi](priorities-launch-first.md) — OBOWIĄZUJĄCA kolejność. **11.08: panel admina kompletny, płatności online DZIAŁAJĄ. NASTĘPNE: backup Etap 1+3, potem nowy serwer.** Czytać PRZED planowaniem sesji.

## Zasady współpracy
- [Nie piętrz zastrzeżeń](feedback-dont-stack-caveats.md) — ryzyko nazwij RAZ i idź dalej. NIE dotyczy faktów nieprawdziwych.
- [Decisions override spec](decisions-override-spec.md) — żywe ustalenia NADRZĘDNE nad docs/specyfikacja.md.
- [References are suggestions](references-are-suggestions.md) — cudze produkty = inspiracja, nie specyfikacja.
- [Incremental checkpoints](incremental-checkpoints-per-element.md) — element po elemencie, przystanek na froncie.
- [CP = commit + push](cp-shorthand-commit-push.md)
- [Pamięć ma kopię w repo](memory-copy-travels-in-repo.md) — **przy CP z handoffem: najpierw `.claude/memory-sync.sh save`, potem commit.** Klucz katalogu liczy się ze ścieżki.
- [Build nie przechodzi = MÓW OD RAZU](build-fails-tell-rafal-immediately.md) — 1–2 próby; Rafał robi build z terminala.
- [No co-author footer](no-coauthor-footer-in-commits.md) — NIGDY stopek generatora w commitach.
- [Ton tekstów marketingowych](feedback-marketing-tone-kramio.md) — Kramio = SPRZEDAŻ, nie oferówka; PRODUKTY, nie usługi; hak = darmowy Kram.

## Prawne (audyt 15.08)
- [DSA: Kramio = HOSTING, nie platforma](legal-dsa-hosting-classification.md) — **DECYZJA Rafała 15.08** + 4 argumenty. **Galeria sklepów na kramio.pl zabiłaby argument nr 1.** Art. 16/17 = ZERO kodu.
- [Regulamin i polityka v3 — OPUBLIKOWANE 15.08](legal-audit-2026-08-15.md) — **UWAGA: numeracja regulaminu się przesunęła** (nowy §11, dawne §11–19 → §12–20). Stare odesłania w notatkach są nieaktualne.

## Otwarte / do decyzji
- [Wzory dokumentów dla sprzedawców](plan-seller-legal-templates.md) — **regulamin ZROBIONY (zweryfikowane 25.08), zostaje POLITYKA PRYWATNOŚCI.** **NIP nie może być twardym warunkiem** (działalność nierejestrowana).
- [ODŁOŻONE: Magellan Bay — osobny projekt obok Kramio](plan-magellan-bay-separate-project.md) — **25.08, ZERO kodu, NIE część roadmapy Kramio.** Rekomendacja: dedykowany sklep na forku; moduł w Kramio i drugi SaaS odrzucone. Dokument dla klienta już opublikowany.
- [Panel admina — KOMPLETNY](plan-admin-panel-and-landing.md) — 11.08 wszystkie działy działają, zero stubów.
- [Wiadomości do sprzedawców](plan-platform-mailing.md) — KOMPLETNE 10.08. **Adresatów pytać TYLKO przez `User::activeMarketingConsent()`.**
- [ZROBIONE: crony ursalogic](disabled-ursalogic-queue-crons.md) — przywrócone 08.08. **12.08 Rafał WSTRZYMAŁ fix `low --max-time=55` do nowego serwera** (29/250 procesów, brak alertów). Wracać tylko przy alertach.
- [OTWARTE: limit 250 procesów](open-hosting-process-limit.md) — **INCYDENT 02.08: pełna suita w kółko zdusiła crony konta → w trakcie pracy testy FILTROWANE, pełna suita raz przed commitem.**
- [KIERUNEK: NOWY SERWER (dev + produkcja)](plan-dev-environment.md) — 11.08 Rafał planuje przeprowadzkę; termin nieustalony.
- [BACKUP: Etap 1+3 WDROŻONE 12.08](open-no-file-backups.md) — **od 17.08 kopia DWA RAZY na dobę (04:00 i 16:00), strażnik 24 h**, ekran Ustawień, **odtworzenie przećwiczone**. Retencja po WIEKU, nie po liczbie plików. **Etap 2 (poza serwerem) otwarty — Rafał odkłada do pierwszych klientów.**
- [ROZEZNANE: Przelewy24](plan-przelewy24-payments.md) — 09.08, ZERO kodu. OSOBNA ścieżka obok Paynow; obowiązkowy `verify` po webhooku.
- [ODŁOŻONE: sklep na cudzej stronie](plan-embed-shop-on-external-site.md) — 15.08, ZERO kodu. **iframe odpada** (ciasteczka third-party, bramki płatności). Rekomendacja: wsad serwerowy katalogu + zakup u nas.
- [DZIAŁA: zakup pakietu online](plan-package-payments.md) — **NIE planować jako „do zrobienia".** Rejestr na 0 zł = nikt nie kupił.

## Pakiety / biznes
- [Cennik pakietów](pricing-packages.md) — Kram 0 / Stragan 750 / Pawilon 1500 zł/rok BRUTTO; drabinka 2,0×.
- [Przekreślona cena — ZAMKNIĘTE 12.08](open-monthly-plan-vs-struck-price.md) — landing pokazuje jawny rachunek (12×75, płacisz za 10), więc jest OK. **Nie podnosić ponownie.** Ocena „UOKiK" była błędna: klient to przedsiębiorca, nie konsument.
- [Opłaty za Kramio](plan-package-payments.md) — cykl DOMKNIĘTY + wpłata ręczna z panelu admina (11.08). Dwie ścieżki, jeden rejestr.
- [Wygaśnięcie pakietu](plan-subscription-expiry.md) — KOMPLETNE 30.07: stan odczytu, karencja 7 dni, maile 14/7/1.
- [Plan: packages](plan-packages.md) — uprawnienia LEPKIE (snapshot per sklep, pakiet = preset).
- [Per-sklep własna cena](plan-per-shop-custom-pricing.md) — NIE betonować „pakiet→funkcje".
- [Gate: edycja zamówienia = Pawilon](gate-order-edit-behind-paid.md)
- [Limity użyć AI per pakiet](plan-ai-usage-limits.md) — tygodniowa pula ZADAŃ (100/400/800).
- [Kody rabatowe](plan-discount-codes.md) — KOMPLETNE: 3 typy, 2 zakresy, rabat rozbijany na pozycje i stawki VAT.
- [Zwroty/odstąpienie 14 dni](legal-consumer-returns-withdrawal.md) — KOMPLETNE 29.07, we WSZYSTKICH pakietach.
- [Kartoteka klientów](plan-customer-directory.md) — 29.07: klucz = ADRES E-MAIL, filtry trójstanowe.
- [Newsletter](plan-bulk-mail.md) — KOMPLETNY 29.07: karencja kalendarzowa, tempo przez `scheduled_at`.
- [Własna analityka](plan-own-analytics.md) — **30.08: admin ma ten sam ekran w przekroju platformy.** Jeden serwis, dwa zakresy (`ShopAnalytics::for(?Shop)`). Rollup wciąż czeka.
- [Dashboard stats direction](dashboard-stats-direction.md) — WŁASNA analityka dla wszystkich; GA/GTM → płatny.
- [Audyt SEO](plan-seo-audit.md) — ZAMKNIĘTY 28.07; luka „tylko storefronty" domknięta 31.07.
- [Mapa strony + Search Console](plan-sitemap-search-console.md) — 03.08 per host. **GOTCHY: `public/robots.txt` MUSI nie istnieć; trasy centrali na KOŃCU routes.**

## Architektura / konwencje
- [Multi-tenant subdomain](multitenant-subdomain-architecture.md) — centrala = zarządzanie; storefront = subdomena; jedna baza + shop_id.
- [Naming & locale](naming-and-locale-convention.md) — URL/interfejs PL, kod EN; pl-first.
- [Frontend stack](frontend-stack-decision.md) — Livewire w panelach; storefront Blade-first; brak JSON API.
- [AI: zadania zamiast modeli](ai-task-profiles-architecture.md) — NIGDY nazwy modelu w kodzie.
- [Storefront theme system](storefront-theme-system.md) · [UI design direction](ui-design-direction.md) — panele „warm boutique".
- [Shared hosting constraints](shared-hosting-constraints.md) — wybieraj lekkie, odporne rozwiązania.
- [Destructive DB guard](defer-destructive-db-guard.md) — prohibitDestructiveCommands + guard testów.
- [App timezone = Europe/Warsaw](app-timezone-warsaw.md) · [Migration to kramio.pl](migration-to-kramio.md) — stary katalog `shop.kwasniak.org` USUNIĘTY 13.08 · [Subdomeny działają](front-blocked-on-subdomain-ssl.md)
- [Input normalization](input-normalization-conventions.md) — normalizuj w prepareForValidation; NIP mod-11.
- [Form client validation](form-client-validation-convention.md) — UX przez forms.js; Form Requesty = źródło prawdy.
- [Grupy radio w forms.js](form-radio-groups-in-forms-js.md) — działają od 15.08. **Zmiana JS: najpierw build, POTEM atrybut w Bladzie.**
- [Text truncation](text-truncation-preserve-words.md) · [Email outbox + cron](email-outbox-cron-pattern.md)

## Gotchas techniczne
- [TESTY NIGDY nie strzelają do API](tests-never-hit-real-apis.md) — **INCYDENT 30.07: ~46 REALNYCH faktur.** `Http::preventStrayRequests()` — nigdy nie zdejmować.
- [Factory na produkcji = konto z hasłem `password`](gotcha-factory-on-production-db.md) — **INCYDENT 16.08, zabezpieczone 24.08 (Warstwa 7).** Podejrzane konto sprawdzaj `Hash::check('password',…)`. `testing.INFO` w logu ≠ testy w produkcyjnej bazie.
- [TESTY NIGDY nie ruszają plików produkcji](tests-never-touch-production-files.md) — **INCYDENT 04.08: skasowany `users/1`.** Garda `isolateDisks()`. GOTCHA: `Storage::fake()` gubi `url`.
- [Tailwind: klasa musi być w buildzie](tailwind-classes-must-exist-in-build.md) — klasa spoza buildu cicho nic nie robi; grepuj `public/build/assets/*.css`.
- [route()/url() na subdomenie](gotcha-route-helpers-on-subdomain.md) — budują link do TEJ subdomeny; `/rejestracja` trafia w rejestrację klienta. Używaj `Central::url()`.
- [Livewire: ostrzejsza sygnatura = fatal](livewire-override-signature-fatal.md) — „Premature end of PHP process" bez przyczyny; kopiuj sygnatury znak w znak.
- [Blade: blok @php psuje @php(...)](blade-php-block-breaks-inline-php.md) — rozwala CAŁY widok.
- [Blade: `@if` przyklejone do słowa NIE kompiluje się](blade-directive-glued-to-word-not-compiled.md) — `@endif` tak → „unexpected token endif", cały widok 500. **Trafione 2×.** Testy `assertRedirect` tego nie łapią.
- [Test: post() + get() gubi flash](gotcha-test-flash-lost-between-requests.md) — błędy i old() nie dożywają; `from()` + `followingRedirects()`.
- [InPost pobranie: `company_data_missing`](gotcha-inpost-cod-requires-company-data.md) — `cod` przechodzi przy obu naszych usługach, ale zakup pada cicho bez pełnych danych firmy. Patrzeć na transakcje, nie na status.
- [Klucz `stall` to Kram, nie Stragan](gotcha-package-key-stall-is-kram.md) — Stragan to `booth`. Sprawdzaj cenę, nie nazwę.
- [NIGDY zmiennej widoku o nazwie $errors](blade-never-name-view-variable-errors.md) — nadpisuje worek walidacji; błąd wskazuje skompilowany widok, nie przyczynę.
- [Safari duch przy transition](safari-opacity-transition-ghost.md) · [Tailwind truncate rozpycha mobile](tailwind-truncate-breaks-mobile.md)
- [Kotwica zjada nagłówek w Safari](safari-anchor-scrolls-overflow-hidden-parent.md) — `overflow-hidden` NIGDY na kontenerze z treścią.
- [Vite build RAYON_NUM_THREADS=1](vite-build-rayon-threads.md) · [Orphaned build processes](orphaned-build-processes-incident.md)

## Moduły WDROŻONE (skrót)
- [Wiadomość serwisowa do sprzedawcy](plan-seller-service-notices.md) — **24.08, commit `a1ad0e8`.** Karta sklepu, **POZA zgodą marketingową — to sens modułu, nie luka.** Oferty nadal tylko działem „Wiadomości".
- [Przesyłki za pobraniem InPost](plan-cash-on-delivery.md) — **17.08, commit `0628285`, zweryfikowane end-to-end.** Pobranie = dwie metody DOSTAWY z własną ceną, nie płatności. **Sklep z samym pobraniem MUSI przyjmować zamówienia** (`acceptsOrders`). Statusy bez zmian.
- [Wolna subdomena zaprasza do rejestracji](plan-unclaimed-subdomain-landing.md) — **12.08**: subdomena bez sklepu = karta „ten adres jest wolny" + prefill rejestracji; `www` już nie 404. **Status zostaje 404/noindex; „wolny" musi zgadzać się z walidacją — pilnuje test.**
- [Sklep bez dostawy i płatności](plan-catalog-mode-no-cart.md) — **09.08**: brak metod → znika „Do koszyka" (`Shop::acceptsOrders()`). Bez przełącznika i komunikatu — świadomie. GOTCHA: `Shop::factory()->sellable()` w testach koszyka.
- [Charakter sklepu](plan-shop-character-axes.md) — **08.08**: czcionka + zaokrąglenia niezależne od szablonu. **TECHNIKA: nadpisywanie zmiennych Tailwinda v4 = ~200 klas bez rebuildu.**
- [Usunięcie sklepu](plan-shop-self-deletion.md) — **04.08**: karencja 7 dni, kwarantanna adresu 90 dni. GOTCHY: kaskada FK nie odpala hooka zdjęć; mail pożegnalny z `shop_id = null`.
- [Zgoda na cookies](plan-cookie-consent.md) — **02.08**: blokada SERWEROWA, zgoda per host, pytamy ZAWSZE.
- [Karta sklepu na Facebooka](plan-shop-og-image-redesign.md) — **02.08**: scena + zdjęcia w perspektywie. GOTCHA: generowanie zablokowane w testach.
- [Odzyskiwanie hasła](handoff-2026-08-02.md) — 02.08: sprzedawca = token brokera, klient = podpisany link per sklep.
- [Fakturownia (FV/KSeF)](plan-fakturownia.md) — **Brak sandboxa: każde żądanie tworzy REALNY dokument.**
- [Płatności Paynow](plan-online-payments-mbank.md) — PER-SKLEP (klucze szyfrowane); opłaty za Kramio = osobne konto platformy.
- [Wysyłki InPost](plan-shipping.md) — **KOMPLETNE 07.08 (sandbox)**. **Data odbioru = start 14 dni na odstąpienie.** GOTCHY: stan „w trakcie" nie po `shipment_id`; telefon bez `+48`. Zostało: test produkcyjny ~20 zł.
- [Kurier InPost](plan-inpost-courier.md) — **08.08, commit `ff9b086`.** GOTCHY: `sending_method` NIEODWRACALNY; `201` ≠ sukces; `Http::fake()` nie nadpisuje pierwszej zaślepki. ZOSTAŁO: produkcja + nadawanie zbiorcze.
- [Zwroty: dwa błędy prawne](plan-shipping.md) — 07.08: termin nie od daty złożenia; formularz po `hasBeenHandedOver`. **Oba znalazł Rafał — dopytywać go o reguły biznesowe.**
- [Zgoda SPRZEDAWCY na oferty](seller-marketing-consent.md) — 30.07: dowód z datą/IP/wersją.
- [Zgoda na mailing klientów](next-marketing-consent.md) — zgoda per SKLEP + link wypisu.
- [Order statuses](plan-order-statuses.md) — mail przy KAŻDEJ zmianie · [panel zamówień](next-orders-panel-tab.md) · [badge](plan-new-orders-badge.md)
- [Customer accounts](plan-customer-accounts.md) — konta per-sklep. Gotcha: w destroy logout PRZED delete.
- [Sale unit](plan-sale-unit-weight.md) · [NIP autofill + Trix](plan-nip-autofill-and-editor.md) · [Shop settings](plan-shop-settings-storage.md) · [Shop edit tabs](plan-shop-edit-tabs.md)
- [Bank transfer](bank-transfer-payment-method.md) — trójpodział dana/metoda/integracja.
- [Shop visibility](shop-visibility-auto-publish.md) — status napędzany aktywnymi produktami (ProductObserver).
- [Omnibus 30d](omnibus-lowest-price-30d.md) · [Prose normalize on output](prose-normalize-on-output.md) — sanitizer = zapis, Prose = wyjście.
- [Wołacz ze słownika](plan-vocative-dictionary.md) — 454 imiona; fallback = MIANOWNIK; zero LLM w runtime.
- [Mail footer](mail-footer-company-data.md) · [bez „automatycznie"](open-mail-footer-contradiction.md) · [tożsamość per sklep](per-shop-email-identity-branding.md)
- [Logo i favicona](brand-logo-assets.md) — 03.08. Płytka pod ikonką celowa; favicon wspólny ze storefrontami.
- [Custom brand color](plan-custom-brand-color.md) · [Storefront theming](plan-storefront-theming.md) · [Editorial + CMS](plan-storefront-editorial-and-pages.md)
- [Informacje left menu](next-informacje-left-menu.md) · [Coming-soon](next-redesign-coming-soon.md) · [Draft preview](storefront-draft-preview.md) · [Image treatment](storefront-image-treatment.md)
- [DeepSeek „Popraw przez AI"](deepseek-ai-improve.md) — zmiana modelu = jedna linia w `.env`.
- [Korekta AI po fragmentach](plan-ai-chunked-correction.md) · [Streaming AI](plan-ai-streaming-response.md) — `AI_STREAMING=false` = wyłącznik.
- [ROZWIĄZANE: zmyślone statystyki](open-landing-fabricated-stats.md) — nie zmyślać liczb na landingu.
- [Audyt obietnic landingu](landing-promises-drift-audit.md) — **landing dryfuje za kodem.** Jedno źródło = `PackageFeatures::highlights()`. **Przy każdym wdrożeniu pytać, czy landing ma o tym mówić.**
- [Stock availability](stock-availability-verification.md) — atomowe zdjęcie stanu + komunikat korekty w koszyku.

## Wizje / do analizy
- [Vision: email-driven orders](vision-email-driven-orders.md) — auto-przejścia statusów + zarządzanie zamówieniem z maila.
- [Shipping aggregator](shipping-aggregator-idea.md) — **ZAMKNIĘTE 08.08: Furgonetka WYPADA** (InPost umie kuriera pod adres).

## Handoffy (najnowsze u góry)
- [2026-08-30 pulpit + analityka platformy + pamięć w repo](handoff-2026-08-30.md) — 3 commity, 1704→1713. **ZAMROŻENIE KODU do przeprowadzki na nowy serwer (tydzień 31.08–06.09).** Migawka „przed" i checklisty w sek. 6 CLAUDE.md; **stary serwer zostaje żywy → admin musi wyłączyć na nim crona.**
- [2026-08-24 awaria rejestracji + korespondencja serwisowa + garda fabryk](handoff-2026-08-24.md) — sesja INTERWENCYJNA, 4 commity, 1693→1704. **2× pierwsza hipoteza groźniejsza niż prawda — rozstrzygał dopiero konkretny dowód.** Pierwsi prawdziwi sprzedawcy już wchodzą.
- [2026-08-17 pobranie InPost + teksty + kopie 2×/dobę](handoff-2026-08-17.md) — 3 commity, 1657→1693. **Sonda na sandboxie przed decyzją — to ona przesądziła sens modułu.** GOTCHY: dokumentacja Confluence obcina się, pełna treść przez REST API; „w którym PAKIECIE", nie „na jakim uprawnieniu". **Rafał 2× przeciął moje myślenie, oba razy trafnie.**
- [2026-08-15 audyt prawny + moduł DSA + dokumenty v3](handoff-2026-08-15.md) — 4 commity, 1653→1657. **Rafał znalazł 5 wad na froncie, w tym grupy radio blokujące wysyłkę.** GOTCHY: Pint tylko na własnych plikach; klasy sprawdzać w buildzie PRZED wysłaniem widoku.
- [2026-08-12 FB + wolna subdomena + BACKUP + nazwa bazy](handoff-2026-08-12.md) — 4 commity, 1599→1631. **Backup DZIAŁA z przećwiczonym odtworzeniem; baza to teraz `host473413_kramio`.** GOTCHY: `route()` na subdomenie celuje w storefront; `diff <(...)` nie działa w tym shellu. **Rafał sprostował 4× — wszystkie trafione.**
- [2026-08-11 panel admina KOMPLETNY](handoff-2026-08-11.md) — `4b18e56`+`47f6443`, 1521→1599. GOTCHY: `$errors` jako zmienna widoku; `assertSee` nie przechodzi przez łamanie linii Blade. **Rafał 3× sprostował.**
- [2026-08-10 Sprzedawcy + Wiadomości](handoff-2026-08-10.md) — `8f58e3a`+`bfa23e7`, 1465→1521. GOTCHY: `assertDontSee` łapie zalogowanego z paska; Pint obejmuje docblocki; kropka w klasie Tailwinda escapowana.
- [2026-08-09 koszyk znika, gdy nie ma jak kupić](handoff-2026-08-09-koszyk.md) — `8aeea14`. **Rafał przeciął diagnozę „usterka" ramą „to legalny sposób użycia".**
- [2026-08-09 limity produktów + sonda P24](handoff-2026-08-09.md) — 24/72/240, `74f589c`. GOTCHA: zmiana limitu wywraca testy zejść z pakietu.
- [2026-08-08 InPost kurierem (sonda)](handoff-2026-08-08-inpost-kurier.md) — **Wzorzec: Rafał przeciął analizę pytaniem „a może po prostu spróbuj".**
- [2026-08-08 charakter sklepu + audyt landingu](handoff-2026-08-08.md) — 1400→1420. Rafał 2× skorygował decyzję na froncie.
- [2026-08-07 InPost ShipX](handoff-2026-08-07-inpost.md) — 1339→1400. **Rafał znalazł 2 błędy prawne — DOPYTYWAĆ o reguły biznesowe.**
- [2026-08-07 szablony + dokumenty prawne v2](handoff-2026-08-07-szablony-i-dokumenty.md) — czeka przegląd prawnika; wzory chrome żyją w 3 miejscach naraz.
- [2026-08-05 garda dysków w testach](handoff-2026-08-05.md) — Warstwa 6 w DB_SECURITY.
- [2026-08-04 usuwanie sklepu + lista admina](handoff-2026-08-04.md) — **GOTCHA: katalog produkcyjny = produkcja, o `migrate --force` pytać OD RAZU.** Własna domena odłożona świadomie.
- [2026-08-03 logo + mapa strony](handoff-2026-08-03.md) — GOTCHY: `line-through`/`gap-1.5` brak w buildzie; `url()` w teście na subdomenie zwraca tę subdomenę; kolumna `published`.
- [2026-08-02 karta sklepu + hasła + cookies](handoff-2026-08-02.md) — GOTCHY: aktywację poznaje się po `email_verified_at`; `config()` NIE DZIAŁA w bootstrap/app.php.
- [2026-07-31 GA na centrali](handoff-2026-07-31-analytics.md) — **3× ta sama luka: funkcja dla SKLEPÓW, nie dla centrali.** Pytać OSOBNO o landing/logowanie/dokumenty.
- [2026-07-31 OG + utwardzenie formularzy](handoff-2026-07-31-security.md) · [gotowe na testerów](handoff-2026-07-31.md) · [07-30 abonament](handoff-2026-07-30.md) · [07-29](handoff-2026-07-29.md)
- [07-28](handoff-2026-07-28.md) · [07-27 SEO](handoff-2026-07-27-seo.md) · [07-27](handoff-2026-07-27.md) · [07-25](handoff-2026-07-25.md) · [07-24](handoff-2026-07-24.md) · [07-20](handoff-2026-07-20.md) · [07-16](handoff-2026-07-16-quick.md)
- [07-15 promoted](handoff-2026-07-15-promoted-pages.md) · [07-15 messenger](handoff-2026-07-15-messenger.md) · [07-14 statusy](handoff-2026-07-14-statuses.md) · [07-14](handoff-2026-07-14.md) · [07-11 produkt](handoff-2026-07-11-product.md) · [07-11 storefront](handoff-2026-07-11-storefront.md)
- [07-11](handoff-2026-07-11.md) · [07-10](handoff-2026-07-10.md) · [07-04](handoff-2026-07-04.md) · [07-03](handoff-2026-07-03.md) · [07-02](handoff-2026-07-02.md) · [07-01](handoff-2026-07-01.md) · [06-30](handoff-2026-06-30.md) · [06-29](handoff-2026-06-29.md)
