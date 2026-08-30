---
name: plan-przelewy24-payments
description: "Przelewy24 jako DRUGI operator obok Paynow — osobna ścieżka, nie wspólna abstrakcja. Rozeznane 2026-08-09, ZERO kodu."
metadata: 
  node_type: memory
  type: project
  originSessionId: 759ad61b-8139-4389-af85-1ced3e332f53
  modified: 2026-08-09T16:22:25.559Z
---

ROZEZNANIE 2026-08-09 (rozmowa, nie kod). Rafał: „P24 są bardzo popularne, może przyjść klient". Nie jest to plan ani zobowiązanie — notatka, żeby nie zaczynać od zera.

**Decyzja kształtu (Rafał):** P24 to **kolejna opcja na liście, OSOBNA ścieżka** — `Przelewy24Service` obok `PaynowService`, własna trasa webhooka, własne logi, własne pola integracji. **NIE łączyć z Paynow** wspólnym interfejsem `PaymentGateway`. Uzasadnienie: bramki różnią się sposobem potwierdzania płatności na tyle, że abstrakcja przeciekłaby — a przeciekająca abstrakcja w kodzie przyjmującym pieniądze jest gorsza niż dwa podobne serwisy. Jedyny styk = zwrotnica w checkoucie (`match` na wybranego operatora). Dla kupującego dalej jedna pozycja „Płatność online".

**Cztery różnice techniczne wobec Paynow:**
1. Auth: Basic (posId:apiKey) + pole `sign` w ciele, zamiast nagłówków `Api-Key`/`Signature`.
2. Podpis: SHA-384 z JSON-a WYBRANYCH pól + klucz CRC. **GOTCHA: zestaw pól jest INNY dla register, inny dla verify, inny dla notyfikacji.** Zły podpis = gołe `401` bez opisu.
3. **Obowiązkowy `PUT /transaction/verify` po webhooku** — sam webhook NIE potwierdza zapłaty (Paynow tak). Pominięcie = przyjmowanie fałszywych potwierdzeń.
4. Rejestracja zwraca `token`, URL przekierowania (`secure.przelewy24.pl/trnRequest/{token}`) składamy sami; Paynow daje gotowy `redirectUrl`.

**Wycena zgrubna:** bramka ~1 dzień; dołożenie wyboru operatora (UI integracji, etykiety, zwrotnica, testy) ~2–3 dni. Statusy zamówień i koszyk BEZ ZMIAN — `PaymentMethod::Online` jest operator-agnostyczne od początku (patrz komentarz w `app/Enums/PaymentMethod.php`).

**Hamulec formalny:** sandbox P24 zakłada się z panelu konta PRODUKCYJNEGO — nie da się zacząć od piaskownicy. Do developmentu trzeba dostępu do sandboxa klienta albo własnego konta produkcyjnego P24. Umowa i weryfikacja sklepu przez P24 są po stronie klienta (klucze per sklep, jak przy Paynow).

**Zastrzeżenie do źródeł:** `developers.przelewy24.pl` zwraca 403 na automatyczne pobranie (i dokumentacji, i YAML-a). Szczegóły składania `sign` są z drugiej ręki (biblioteka `mnastalski/przelewy24-php` + omówienia) — przed pisaniem kodu zweryfikować na oficjalnym YAML-u.

Powiązane: [[plan-online-payments-mbank]] (Paynow per-sklep, wzorzec do skopiowania), [[plan-package-payments]] (opłaty za Kramio = OSOBNE konto platformy, nie ruszać).
