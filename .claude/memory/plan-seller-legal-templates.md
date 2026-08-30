---
name: plan-seller-legal-templates
description: "CZĘŚCIOWO ZROBIONE: wzór REGULAMINU sprzedawcy istnieje w kodzie (zweryfikowane 25.08). Zostaje wzór POLITYKI PRYWATNOŚCI. Plan flow ustalony 15.08."
metadata: 
  node_type: memory
  type: project
  originSessionId: 4d8bfafa-24ef-47c8-8b0f-9bb8f207d0e5
  modified: 2026-08-25T07:22:50.685Z
---

> **KOREKTA 2026-08-25 (zweryfikowane w kodzie):** „zero kodu" jest już NIEAKTUALNE. Wzór **regulaminu** został zbudowany 16–17.08: `resources/views/seller/legal/templates/regulamin.blade.php` (236 linii, realny szablon — nie zaślepka), plus kolumny `terms_answers` i `terms_template_version` na tabeli `pages` i obsługa w `App\Http\Controllers\Seller\PageController`. **Nie budować go drugi raz.**
>
> **Zostaje wzór POLITYKI PRYWATNOŚCI** — nie istnieje w żadnej formie (mamy tylko własną politykę Kramio). Kolejność opisana niżej („najpierw polityka") została więc w praktyce odwrócona; powody, dla których polityka jest ważniejsza, pozostają aktualne.

**Ustalone z Rafałem 2026-08-15 wieczorem. Poniżej plan flow — nadal obowiązuje dla polityki.**

Problem: §10 ust. 1 naszego regulaminu wymaga, żeby sprzedawca opublikował własny regulamin sprzedaży, a §17 czyni go administratorem danych klientów. Nie dajemy mu narzędzia do żadnego z tych obowiązków. Landing obiecuje sklep „w 15 minut", a zgodnie z prawem nie da się w tym czasie wystartować — obietnica ma cichy warunek, o którym nie mówimy.

## FLOW — pomysł Rafała, przyjęty

1. Po założeniu sklepu **nic się nie zmienia** — żaden dokument nie pojawia się w imieniu sprzedawcy.
2. W panelu, na podstronie dokumentu, przycisk **„Wstaw wzór…"** (NIE „Pobierz" — to sugeruje plik na dysk).
3. Kliknięcie podmienia treść podstrony na nasz wzór, wypełniony danymi sklepu.
4. Bramka: najpierw trzeba uzupełnić dane, których wzór wymaga. Brakujące pola wypisane wprost.

Mocne strony tego układu: sprzedawca **sam po to sięga** (właściwa postawa prawna), a bramka na dane zamienia uciążliwy obowiązek w warunek czegoś, czego chce — przy okazji podnosi jakość danych w całej platformie.

## KOLEJNOŚĆ: NAJPIERW POLITYKA PRYWATNOŚCI

Odwrotnie, niż asystent proponował na początku. Powody:
- Polityka jest potrzebna od **pierwszego adresu e-mail** (newsletter, konto klienta), regulamin dopiero od pierwszej sprzedaży.
- **Umiemy ją napisać lepiej niż sprzedawca** — w ~90% opisuje NASZĄ infrastrukturę (jakie dane płyną, gdzie trafiają, podwykonawcy: Paynow, InPost, Fakturownia, hosting), a nie jego biznes.
- Wymaga od sprzedawcy **niemal zerowego wkładu**, więc na niej przechodzimy cały mechanizm (przycisk → braki → podmiana → szkic → wersjonowanie). Regulamin dokłada potem tylko kreator z pytaniami.

## PUŁAPKA: NIP NIE MOŻE BYĆ TWARDYM WARUNKIEM

§6 ust. 2 dopuszcza **działalność nierejestrowaną**, a `Shop::packageInvoiceRecipient()` ma już flagę `personal` (`company_name === '' || nip === ''`) dokładnie na ten przypadek. Zablokowanie wzoru do czasu podania NIP-u odcięłoby grupę, do której celujemy najmocniej. Wzór musi mieć **dwa warianty klauzuli o sprzedawcy**: firma z NIP-em albo osoba fizyczna z informacją o działalności nierejestrowanej.

## CO JUŻ MAMY, A CZEGO MUSIMY ZAPYTAĆ

**Mamy w bazie** (`Shop`): `company_name`, `nip`, adres (`addressComplete()`), `contact_email`, `contact_phone` oraz komplet metod — `bank_transfer_enabled`, `pickup_enabled`, `pay_on_pickup_enabled`, `courier_enabled`, `parcel_locker_enabled` z kosztami. To wystarczy, żeby paragrafy o płatnościach i dostawie napisały się same i były PRAWDZIWE.

**Musimy zapytać (tylko regulamin, 3 pytania):**
- czy sprzedaje **treści cyfrowe** (pliki, dostęp online) — art. 38 pkt 13 u.p.k., moment wykonania
- czy ma towary **wyłączone z prawa odstąpienia** (personalizowane, szybko psujące się)
- **adres do zwrotów**, jeśli inny niż adres firmy

## HAKI JUŻ W KODZIE

`config/pages.php` ma gotowe wpisy `regulamin` i `privacy` z **zaślepkami** („Regulamin naszego sklepu jest właśnie w przygotowaniu"). Czyli dziś każdy sklep publikuje klientom komunikat, że dokumentu nie ma — funkcja nie tylko coś dodaje, ale **zasypuje istniejącą dziurę**.

## WYMAGANIA, NA KTÓRYCH SIĘ UPIERAMY

- **Wstawiamy jako SZKIC, nie publikujemy.** Publikacja to osobne kliknięcie sprzedawcy — od tej sekundy to jego dokument.
- **Potwierdzenie przy nadpisaniu**, gdy sprzedawca napisał już coś swojego. Gdy stoi tam nasza zaślepka — podmieniamy bez pytania.
- **Słowo „wzór" wszędzie** — w przycisku, komunikacie i regulaminie platformy. Nie „gotowy regulamin".
- **Zapisujemy WERSJĘ wzoru** (jedna kolumna). Bez tego po aktualizacji przez prawnika nie będziemy wiedzieli, kto ma starą — a później się tego nie dorobi.
- **ZERO AI w runtime.** Szablon z polami (Blade + dane sklepu), nie model językowy. Ta sama zasada co przy [[plan-vocative-dictionary]]: deterministyczne, przejrzane raz, bez zmyśleń.

## PRAWNIK

Napisać wzory u nas, zbudować flow, wygenerować **trzy przykładowe wyniki** (firma z NIP-em / działalność nierejestrowana / sklep z treściami cyfrowymi) i **to** dać do przeglądu. Ocenianie abstrakcyjnego szablonu z lukami jest drogie, ocenianie gotowych dokumentów szybkie. **Oba wzory w jednym zleceniu**, razem z pytaniem o kwalifikację DSA — patrz [[legal-dsa-hosting-classification]]. Do sprzedawców wypuszczamy dopiero po akceptacji.

Wtedy zastrzeżenie brzmi mocno: „wzór przygotowany przez radcę prawnego, uzupełniony Twoimi danymi — przeczytaj i opublikuj pod własną odpowiedzialnością", a nie „AI coś napisało, sprawdź sam".

## STAŁY OBOWIĄZEK, KTÓRY BIERZEMY NA SIEBIE

Wzór polityki opisuje NASZYCH podwykonawców. Zmiana operatora płatności albo nowa integracja = polityki opublikowane przez sprzedawców robią się nieaktualne, a stoi na nich ich nazwisko. §18 ust. 5 regulaminu już zobowiązuje nas do uprzedzania o zmianach na liście podwykonawców — trzeba dołożyć do tego powiadomienia „zaktualizuj wzór w swoim sklepie". Stąd sens numerowania wersji.

**Nie jest to argument przeciw — to rzecz do zaplanowania OD RAZU.**

Powiązane: [[legal-audit-2026-08-15]], [[plan-storefront-editorial-and-pages]], [[handoff-2026-08-15]].
