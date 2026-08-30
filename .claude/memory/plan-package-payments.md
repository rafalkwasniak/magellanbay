---
name: plan-package-payments
description: "Opłaty za pakiety Kramio — cykl sprzedaży DOMKNIĘTY; zakup online przez Paynow DZIAŁA na kluczach produkcyjnych, obok niego rejestracja wpłaty ręcznej z panelu admina"
metadata: 
  node_type: memory
  type: project
  originSessionId: 6850d835-d66a-4f18-acd6-ca84588d5ea8
  modified: 2026-08-11T18:10:05.398Z
---

Sprzedaż pakietów Kramio ma **dwie równoległe, działające ścieżki** — obie zapisują do tego samego rejestru `package_payments`, więc przychód liczy się z jednego źródła.

**1. Zakup online (Paynow, konto PLATFORMY) — DZIAŁA NA PRODUKCJI.**
- `POST /sprzedawca/pakiet/kup/{package}` → `Seller\PackageController::purchase` → `PackagePaymentService::start()` → przekierowanie do Paynow.
- Webhook: `POST /platnosci/paynow/pakiety/webhook` (`payments.paynow.packages.webhook`) → `PackagePaymentService::apply()` → pakiet + termin + faktura (Fakturownia) + mail.
- Klucze w `.env`: `PAYNOW_PLATFORM_API_KEY`, `PAYNOW_PLATFORM_SIGNATURE_KEY`, `PAYNOW_PLATFORM_ENVIRONMENT=production`. To OSOBNE konto od kluczy per-sklep ([[plan-online-payments-mbank]]).
- Twarda bramka przed płatnością: `Shop::canBeInvoicedForPackage()` — bez danych do faktury nie przyjmujemy pieniędzy.
- Zgody i akceptacje mamy (Rafał, 08.08).

**2. Wpłata ręczna (przelew / gotówka) — od 11.08.2026.**
- `/administrator/pakiety/oplaty/nowa`, komponent `Administrator\PackagePaymentRecorder`.
- Zapis **ustawia pakiet i termin** przez `PackagePaymentService::record()`, nie jest samą notatką.
- Faktura domyślnie WYŁĄCZONA (Fakturownia bez sandboxa = realny dokument); zamiast niej można wpisać numer FV wystawionej poza systemem.

**Why:** dopóki pakiety sprzedaje się też z ręki, liczenie przychodu wyłącznie z bramki pokazywałoby zero mimo realnych pieniędzy. Dwie ścieżki, jeden rejestr — zero rozjazdu między „zapłacił" a „ma pakiet".

**How to apply:** planując prace, NIE traktować zakupu online jako „do zrobienia" — to gotowe i wpięte na produkcję. Rejestr pokazujący 0 zł znaczy „nikt jeszcze nie kupił", nie „nie ma czym kupić". Komentarz w `routes/web.php` mówiący, że „zakup dojdzie osobno", był nieaktualny i raz wprowadził asystenta w błąd — usunięty 11.08. Zobacz też [[plan-admin-packages-section]] i [[decisions-override-spec]].
