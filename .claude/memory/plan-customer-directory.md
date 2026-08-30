---
name: plan-customer-directory
description: "Kartoteka klientów w panelu sprzedawcy — WDROŻONA 2026-07-29 (e3a4449). Klucz = ADRES E-MAIL (goście razem z kontami), we wszystkich pakietach, filtry trójstanowe. Dział zauważony przez Rafała jako brak — nie było go w żadnym planie."
metadata: 
  node_type: memory
  type: project
  originSessionId: 97c7f155-ba79-478d-99e9-6f752187fda0
  modified: 2026-07-29T20:32:33.451Z
---

**WDROŻONE 2026-07-29, commit `e3a4449`.** Dział „Klienci" (menu 👥, między Zamówieniami a Kodami rabatowymi): `/sprzedawca/klienci` + karta `/sprzedawca/klienci/{email}`.

**Jak powstał:** Rafał zauważył brak — *„właściciel sklepu nie ma kartoteki swoich klientów?"*. Tego działu NIE BYŁO w żadnym planie ani na liście priorytetów, choć jest podstawą obsługi sprzedaży. Analityka pokazywała tylko agregaty (nowi vs powracający, top 5 w oknie) — to statystyka, nie kartoteka: nie dało się znaleźć konkretnej osoby ani zobaczyć jej historii. Weszło PRZED płatnościami za pakiety (decyzja Rafała), bo brak kartoteki boli codziennie, a brak automatu do pakietów raz na klienta.

## Decyzje, które będą się powtarzać (np. w panelu admina)

**1. Kluczem jest ADRES E-MAIL, nie konto klienta.** Większość zamówień składają goście, więc kartoteka oparta o tabelę `customers` pokazałaby ułamek realnych klientów. Konto (gdy istnieje) dokłada się do wiersza jako znacznik + zgoda marektingowa. Adresy porównywane bez względu na wielkość liter (`mb_strtolower`) — „Anna@" i „anna@" to jedna osoba. Konsekwencja: **identyfikatorem w URL jest e-mail** (`->where('email', '.*')` przepuszcza kropki i małpę), bo gość nie ma `id`.

**2. Dwie liczby, które łatwo pomylić.** Liczba zamówień = WSZYSTKIE, także anulowane (to historia kontaktu). Wydatki = tylko zamówienia liczone jako zakup. Gdy anulowane istnieją, karta pokazuje osobną linię „w tym anulowane", inaczej liczby wyglądają na niezgodne. Średnia liczona z zapłaconych — anulowane zaniżałyby ją bez powodu.

**3. Dane osobowe z NAJNOWSZEGO zamówienia** (albo z konta, gdy jest). Nazwisko czy telefon mogły się zmienić, a kartoteka ma pokazywać stan bieżący. GOTCHA w testach: fabryka zamówień losuje `buyer_name`, więc test musi ustawić dane na KAŻDYM zamówieniu, nie tylko pierwszym.

**4. Filtry TRÓJSTANOWE** (`konto`, `zgoda`): brak parametru = „bez znaczenia", `1` = tak, `0` = nie. Bez tego nie da się wskazać klientów BEZ konta — „0" i „brak" znaczyłyby to samo. Praktyczny zysk: filtr „zgodził się" daje gotową listę odbiorców mailingu ([[plan-bulk-mail]]).

**5. We WSZYSTKICH pakietach**, także darmowym Kramie — to narzędzie obsługi sprzedaży jak zakładka Zamówienia, nie funkcja premium. Spójne z „własna analityka dla wszystkich, GA płatne" ([[dashboard-stats-direction]]).

## Spójność UI (żądanie Rafała, warto trzymać dalej)
Ekrany listowe w panelu mają wyglądać JAK ZAMÓWIENIA: dwie kolumny (8/4), sortowanie selectem przy nagłówku listy (nie w filtrach, z hidden polami niosącymi filtry), panel „Filtry" z małym linkiem „Wyczyść", opcje „— dowolne —", pełnej szerokości przycisk **„Filtruj"** (`border-amber-200 bg-amber-50 text-amber-800`), kafelek podsumowania pod filtrami liczony z CAŁEGO wyświetlonego zbioru, pusty wynik z ikoną 🔍 i przyciskiem „Wyczyść filtry", powrót jako **„← Wróć do listy"**.

## Pliki
`App\Services\CustomerDirectory` (all/search/profile), `Seller\CustomerController`, `seller/customers/{index,show}.blade.php`. Testy: `Seller/CustomerDirectoryTest` (9), `Seller/CustomerPanelTest` (12).

Powiązane: [[plan-customer-accounts]] (konta per-sklep), [[plan-own-analytics]] (agregaty, nie kartoteka), [[plan-discount-codes]] („Wystaw kod" z karty), [[handoff-2026-07-29]].
