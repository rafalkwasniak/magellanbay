---
name: plan-staff-accounts-and-permissions
description: "Konta pracowników z prawami do działów — plan dla Kramio (funkcja płatna) i Magellana (do wdrożenia, nie teraz)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 43b6e9cc-a378-4a18-8cf7-b97d8a63be16
  modified: 2026-09-06T17:57:27.546Z
---

# Pracownicy i uprawnienia do działów

Pomysł Rafała z 06.09.2026. **Nic nie zaczęte, zero kodu.** W Magellanie do
wdrożenia, ale nie teraz; w Kramio jako funkcja płatna — do decyzji cenowej.

Potrzeba: „ktoś się loguje i np. dodaje produkty, ale nie może nic więcej".

## Pomysł architektoniczny: uprawnienia po PREFIKSIE TRASY

Trasy panelu **już są pogrupowane po działach** (`seller.products.*`,
`seller.orders.*`, `seller.settlements.*`). To gotowa mapa uprawnień — nie
trzeba jej tworzyć ani oznaczać kontrolerów ręcznie.

- `config/staff.php` deklaruje działy: klucz, etykieta, prefiks trasy, czy delegowalny.
- **Jeden** middleware na całej grupie `seller` sprawdza dostęp do działu
  wynikającego z nazwy bieżącej trasy.
- Menu filtruje się tym samym warunkiem — pracownik widzi tylko swoje pozycje.

**Nowy moduł = jeden wpis w configu, zero zmian w middleware.**

## Dlaczego to rozwiązuje rozbieżność produktów

Kramio i Magellan mają **różne listy działów** (Magellan ma dodatkowo Katalog,
Personalizację, Partnerów, Rozliczenia). Ale różnica dotyczy LISTY, nie
mechanizmu — a lista siedzi w configu. Ten sam kod obsługuje oba, tak jak
`CatalogAxis` obsługuje dowolne osie katalogu.

## Cztery decyzje do podjęcia przed pisaniem

1. **Nie wszystkie działy wolno delegować.** Ustawienia, Integracje, Mój sklep
   i Rozliczenia powinny zostać WYŁĄCZNIE właściciela — twardo w configu, nie
   jako opcja do odznaczenia. Pracownik dodający produkty nie może mieć dostępu
   do kluczy Paynow ani danych do przelewu.
2. **Właściciel zawsze ma wszystko** i nie da się tego odebrać — inaczej ktoś
   odbierze sobie prawa i zostanie z zablokowanym sklepem.
3. **Zamówienia to osobny przypadek:** „widzi" ≠ „może edytować". Przez edycję
   zamówienia idą pieniądze, więc tam zamiast tak/nie przydają się dwa poziomy.
   Jedyne miejsce wymagające czegoś więcej niż prostej bramki.
4. **Bramkowanie pakietem w Kramio to zero dodatkowej pracy** —
   `entitlement('staff_limit')` (0 / 0 / 5 albo 0 / 2 / 10, do decyzji
   cenowej). W Magellanie pakiet `dedicated` ma otwarte, jak wszystko inne.

## Warstwa danych (szkic)

Tabela `shop_users`: `shop_id`, `user_id`, uprawnienia (JSON albo pivot),
`invited_at`. Zaproszenie mailem z ustawieniem hasła — jak przy rejestracji
sprzedawcy.

Uwaga: dziś **sklep ma dokładnie jednego właściciela** (`shops.owner_id`,
`hasOne`), i to jest przyczyna, dla której Rafał nie ma drugiego konta do
panelu Magellana — patrz `docs_mod/SETUP.md`, sekcja o dostępie serwisowym.
Ten moduł jest właściwym rozwiązaniem także tamtej potrzeby.

## Gdzie to zapisać przy pracy nad Kramio

Ta notatka leży w pamięci **Magellana**. Przy powrocie do Kramio trzeba ją tam
przenieść, bo decyzja cenowa i wdrożenie dotyczą obu produktów.

Pokrewne: [[plan-packages]], [[pricing-packages]], [[gate-order-edit-behind-paid]],
[[multitenant-subdomain-architecture]].
