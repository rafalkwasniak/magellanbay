---
name: multitenant-subdomain-architecture
description: "Centrala = główna domena (zarządzanie); storefront = subdomena {shop}.central; jedna baza + shop_id."
metadata: 
  node_type: memory
  type: project
  originSessionId: eb5e9cc9-94cf-4010-b386-8ca993d18870
---

Ustalone 2026-06-25. Sklep jest wielonajemczy przez **subdomeny**:

- **Centrala** = domena platformy `config('tenancy.central_domain')` (env `APP_DOMAIN`, prod = `shop.kwasniak.org`): logowanie, rejestracja, panel sprzedawcy (`/sprzedawca`) i admina (`/administrator`). **Sprzedawca zarządza sklepem w centrali — NIE loguje się do swojej subdomeny, by nim zarządzać** (na subdomenie może co najwyżej być własnym klientem).
- **Storefront** = subdomena `{shop}.{central_domain}` (np. `bukiety.shop.kwasniak.org`): publiczny sklep jednego sprzedawcy. `{shop}` = slug = etykieta subdomeny; middleware (np. `ResolveShop`) rozwiązuje model `Shop` i scope'uje wszystko do niego. Inne kontrolery niż centrala.
- **Jedna baza + `shop_id`** na tabelach najemcy (nie baza-per-sklep — przerost na shared-hoście, [[shared-hosting-constraints]]).

**Stan/decyzja o proporcji:** wildcard DNS (`*.shop.kwasniak.org`) już rozwiązuje się na właściwy IP, ale serwer WWW jeszcze NIE kieruje subdomen do aplikacji (zwraca 404) — brakuje wildcard vhost + wildcard SSL. **To działka Rafała, świadomie odłożona** — pracujemy na domenie centrali jeszcze długo.

W kodzie architektura jest **przewidziana, ale nie wymuszona**: `config/tenancy.php` (`central_domain`) gotowe; w `routes/web.php` sekcja CENTRALA + udokumentowany WYŁĄCZONY szkielet grupy STOREFRONT (`Route::domain('{shop}.'.config('tenancy.central_domain'))`). Celowo NIE wiążemy tras przez `Route::domain` teraz — dopóki subdomeny nie są włączone, wiązanie tylko dodaje kruchość (localhost/www/testy) bez korzyści. Włączymy jednym ruchem przy budowie storefrontu.

**How to apply:** każdą nową trasę przypisuj świadomie do centrali albo storefrontu. Tabele najemcy projektuj z `shop_id`. Model `Shop` ze `slug` to najbliższy fundament. Powiązane: [[naming-and-locale-convention]], [[storefront-theme-system]], [[frontend-stack-decision]].
