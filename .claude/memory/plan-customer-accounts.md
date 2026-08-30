---
name: plan-customer-accounts
description: "Konta klientów storefrontu — W TOKU: fundament danych + rejestracja/aktywacja/logowanie ZROBIONE; dalej kasa + Moje konto."
metadata: 
  node_type: memory
  type: project
  originSessionId: 3ded77b3-88a5-4ebf-845d-7c42f3bcc88a
---

**Konta Klientów na storefroncie** (klient per-sklep, guard `customer`, pełna izolacja między sklepami — ten sam e-mail = różne konta). Budowa 2026-07-13, element po elemencie ([[incremental-checkpoints-per-element]]); po każdym kroku CP + realny test w sklepie.

**Model uzgodniony z Rafałem (2026-07-13):**
- Aktywacja e-mailem WSZĘDZIE (double-opt-in): także rejestracja w kasie → mail aktywacyjny, brak hasła w kasie, po kasie klient NIE jest od razu zalogowany.
- W kasie gdy e-mail MA już konto w tym sklepie → **cicho dopisz** zamówienie (customer_id) bez logowania.
- Standalone rejestracja = minimum e-mail (telefon opcjonalny, mimo że spec chce wymaganego).

**ZROBIONE — Krok 1 (fundament danych):** tabela `customers` (shop_id, name/surname/email/phone, password+email_verified_at nullable, unique [shop_id,email]); FK `orders.customer_id`→customers (nullOnDelete); model `Customer` (Authenticatable, MustVerifyEmail, isActivated(), claimGuestOrders()); guard `customer`+provider `customers` w config/auth.php; Shop::customers(), Order::customer(); CustomerFactory. Commit 3a0f822.

**ZROBIONE — Krok 2 (rejestracja/aktywacja/logowanie):** trasy w grupie subdomenowej (StorefrontRegister/Activation/Auth); rejestracja bez hasła + mail aktywacyjny brandowany „od sklepu" (CustomerActivationMailer, outbox); **aktywacja przez PODPISANY link** (`URL::temporarySignedRoute` + middleware `signed`) — świadomie BEZ brokera `activation`/tabeli password_reset_tokens, bo ta jest kluczowana samym e-mailem → kolizja przy tym samym mailu w wielu sklepach; podpisany link keyed po globalnie unikalnym customer.id to omija; aktywacja ustawia hasło, przypina zamówienia gościa (claimGuestOrders, case-insensitive, tylko ten sklep), auto-login; logowanie/wylogowanie scope po shop_id; nagłówek storefrontu Zaloguj/Wyloguj. 4 widoki storefront/auth/*. Commit 36d081b. 463 testy. Zweryfikowane na żywo na ilikemybike.kramio.pl.

**ZROBIONE — Krok 3 (kasa):** OrderService::place(Shop,$data,?authCustomer) + resolveCustomer(): (1) zalogowany klient tego sklepu, (2) e-mail z kontem → cicho dopisz customer_id (bez maila), (3) „załóż konto" na wolnym e-mailu → nowe konto NIEAKTYWNE z danymi z kasy + mail aktywacyjny po commicie, (4) gość. createOrder ustawia customer_id. Checkout Livewire: prefill dla zalogowanego, computed authCustomer/accountExists, checkbox „Załóż konto" (e-mail wire:model.blur; gdy konto istnieje → info+link logowania). Commit 2d3c966. 467 testów. Zweryfikowane realnie: place() na żywej MySQL w transakcji z rollbackiem.

**ZROBIONE — Krok 4 (Moje konto):** middleware `auth.customer` (gość→/logowanie z zapamiętanym celem); AccountController — historia zamówień + szczegół (404 dla cudzego), edycja danych (ProfileUpdateRequest, telefon normalizowany), zmiana hasła (`current_password:customer`), usunięcie konta (RODO). Widoki storefront/account/* (nav zakładek). Nagłówek zalogowanego → „Moje konto". Commit 35db73a. 473 testy. Zweryfikowane na żywo end-to-end.
  - **GOTCHA (ważne):** w `destroy` NAJPIERW logout, POTEM delete. `SessionGuard::logout()` cyklizuje remember-token i ZAPISUJE usera → delete-przed-logout robił INSERT wskrzeszający właśnie usunięte konto (SQLite reużywał id). Ta sama pułapka dotyczyłaby usuwania konta sprzedawcy.

**MODUŁ KONT KLIENTÓW DOMKNIĘTY** (Kroki 1–4). Możliwe rozszerzenia później: reset hasła klienta (forgot password — dziś brak), ponowne wysłanie linku aktywacyjnego, adresy domyślne/firma w profilu, zgoda Regulamin/PP przy rejestracji, RODO-eksport danych.
- Do rozważenia później: reset hasła klienta (forgot password) — dziś brak; ponowne wysłanie linku aktywacyjnego; zgoda Regulamin/PP przy rejestracji (dziś brak — konto to tylko e-mail+hasło).

Powiązane: [[multitenant-subdomain-architecture]], [[vision-email-driven-orders]], [[per-shop-email-identity-branding]].
