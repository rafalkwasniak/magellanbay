<?php

namespace App\Support;

use App\Models\OptionGroup;
use App\Models\Product;

/**
 * Konfiguracja personalizacji jednej pozycji koszyka — normalizacja, klucz
 * pozycji i dopłata.
 *
 * DLACZEGO OSOBNA KLASA. To samo pytanie zadają cztery różne miejsca: koszyk
 * (czy dwie pozycje to ta sama rzecz), kasa (ile dopłacić), złożenie zamówienia
 * (co zapisać w pozycji) i arkusz produkcyjny (co wygrawerować). Rozsypane po
 * nich, rozjechałyby się przy pierwszej zmianie reguł — a rozjazd między
 * „ile policzyliśmy" a „co wykonamy" jest najdroższym błędem tego modułu.
 *
 * CO TRAFIA DO SESJI. Wyłącznie WYBÓR kupującego: identyfikatory pozycji
 * biblioteki i wpisany tekst. Nigdy ceny — te liczymy z bazy przy każdym
 * renderze, dokładnie jak `CartService` robi to od początku z ceną produktu.
 * Dzięki temu podmiana wartości w sesji nie kupi niczego taniej.
 *
 * KSZTAŁT ZNORMALIZOWANY:
 *
 *     [
 *       12 => ['choice' => 87],                       // grupa `choice`
 *       15 => ['fields' => [3 => 'Zosia', 4 => '2026']], // grupa `text`
 *     ]
 *
 * Grupy i pola posortowane po identyfikatorze, wartości przycięte, puste
 * usunięte. Bez tego „Zosia" wpisana w innej kolejności pól dałaby inny klucz
 * i ta sama rzecz leżałaby w koszyku dwa razy.
 */
final class ProductConfiguration
{
    /**
     * Sprowadza surowe wejście z formularza do postaci kanonicznej albo ODRZUCA
     * je w całości.
     *
     * ODRZUCAMY, ZAMIAST POPRAWIAĆ — świadomie. Przycięcie za długiego imienia
     * do limitu wyprodukowałoby magnes z uciętym napisem, za który klient
     * zapłacił i którego nie może zwrócić (produkt personalizowany, art. 38
     * pkt 3 u.p.k.). Lepiej nie przyjąć pozycji do koszyka, niż wykonać ją źle.
     *
     * `null` znaczy „ta konfiguracja jest nie do przyjęcia". Wołający decyduje,
     * czy pokazać błąd (formularz), czy po prostu zignorować (dodanie do koszyka
     * z palca) — tak samo jak `CartService::add()` ignoruje cudzy produkt.
     *
     * @param  array<mixed>  $input
     * @return array<int, array<string, mixed>>|null
     */
    public static function normalise(Product $product, array $input): ?array
    {
        $groups = $product->optionGroups()->with(['fields', 'choices'])->get()->keyBy('id');

        // Wejście wskazujące grupę spoza produktu to albo pomyłka, albo próba
        // doklejenia cudzej opcji. W obu przypadkach nie zgadujemy intencji.
        foreach (array_keys($input) as $groupId) {
            if (! $groups->has((int) $groupId)) {
                return null;
            }
        }

        $out = [];

        foreach ($groups as $group) {
            $answer = self::normaliseGroup($group, $input[$group->id] ?? []);

            if ($answer === false) {
                return null;
            }

            if ($answer !== null) {
                $out[$group->id] = $answer;
            }
        }

        if (! self::exclusionsRespected($groups->all(), $out)) {
            return null;
        }

        ksort($out);

        return $out;
    }

    /**
     * Odpowiedź na jedną grupę: tablica (wypełniona), `null` (pominięta,
     * dozwolone przy grupie nieobowiązkowej) albo `false` (błąd).
     *
     * @return array<string, mixed>|null|false
     */
    private static function normaliseGroup(OptionGroup $group, mixed $input): array|null|false
    {
        $input = is_array($input) ? $input : [];

        return $group->isChoice()
            ? self::normaliseChoice($group, $input)
            : self::normaliseFields($group, $input);
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>|null|false
     */
    private static function normaliseChoice(OptionGroup $group, array $input): array|null|false
    {
        $choiceId = isset($input['choice']) ? (int) $input['choice'] : 0;

        if ($choiceId === 0) {
            return $group->required ? false : null;
        }

        // Tylko pozycja AKTYWNA i należąca do tej grupy. Wycofana zostaje
        // w bazie ze względu na historyczne zamówienia, ale nie wolno jej
        // dziś wybrać.
        $choice = $group->choices->first(
            fn ($c) => $c->id === $choiceId && $c->is_active
        );

        return $choice === null ? false : ['choice' => $choice->id];
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>|null|false
     */
    private static function normaliseFields(OptionGroup $group, array $input): array|null|false
    {
        $raw = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        $values = [];

        foreach ($group->fields as $field) {
            // `Str::squish` nie wystarczy: liczy się też to, że klient wkleja
            // teksty z niewidocznymi spacjami na końcu, a te trafiłyby na wydruk.
            $value = trim((string) ($raw[$field->id] ?? ''));

            if ($value === '') {
                if ($field->required) {
                    return false;
                }

                continue;
            }

            // Limit wynika z fizyki produktu — dłuższy tekst to zamówienie
            // niewykonalne, więc nie przycinamy, tylko odrzucamy.
            if (mb_strlen($value) > $field->max_length) {
                return false;
            }

            $values[$field->id] = $value;
        }

        if ($values === []) {
            return $group->required ? false : null;
        }

        ksort($values);

        return ['fields' => $values];
    }

    /**
     * Grupy wykluczające się nie mogą być wypełnione obie — „grawer to grafika
     * ALBO tekst, nigdy oba".
     *
     * Sprawdzamy OBIE strony wskazania, bo wykluczenie zapisuje tylko jedna
     * grupa, a znaczy dla obu.
     *
     * @param  array<int, OptionGroup>  $groups
     * @param  array<int, mixed>  $answers
     */
    private static function exclusionsRespected(array $groups, array $answers): bool
    {
        foreach ($groups as $group) {
            $other = $group->excludes_group_id;

            if ($other === null) {
                continue;
            }

            if (isset($answers[$group->id], $answers[$other])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Klucz pozycji koszyka.
     *
     * DWIE POZYCJE TEGO SAMEGO PRODUKTU SĄ TĄ SAMĄ POZYCJĄ TYLKO WTEDY, gdy mają
     * identyczną konfigurację. Magnes z imieniem „Zosia" i magnes z imieniem
     * „Antek" to dwie różne rzeczy do wykonania, choć jeden produkt w katalogu —
     * i dokładnie dlatego `product_id` przestał wystarczać jako klucz.
     *
     * Produkt bez konfiguracji zachowuje czytelny klucz `p12`, żeby zwykły
     * koszyk dało się przeczytać w sesji gołym okiem przy diagnozie.
     *
     * @param  array<int, mixed>  $configuration
     */
    public static function key(int $productId, array $configuration): string
    {
        if ($configuration === []) {
            return 'p'.$productId;
        }

        // Pełny skrót, nie skrócony: kolizja sklejałaby dwie RÓŻNE konfiguracje
        // w jedną pozycję, czyli po cichu zmieniała komuś zamówienie.
        return 'p'.$productId.':'.sha1((string) json_encode($configuration));
    }

    /**
     * Dopłata brutto za konfigurację — DOPŁATA DO CENY PRODUKTU, nie cena
     * zamiast niej (ustalenie z klientem).
     *
     * Liczona zawsze ze świeżych danych, nigdy z sesji. Na dopłatę składa się
     * koszt skorzystania z grupy (np. wykonanie graweru) oraz dopłata wybranej
     * pozycji biblioteki (np. konkretna grafika).
     *
     * Opłat licencyjnych TU NIE MA — rządzi nimi reguła „suma po licencjodawcach,
     * maksimum wewnątrz jednego", więc nie da się ich policzyć składnikiem po
     * składniku. Dochodzą osobno, razem z kartoteką licencjodawców.
     *
     * @param  array<int, mixed>  $configuration
     */
    public static function surcharge(Product $product, array $configuration): float
    {
        if ($configuration === []) {
            return 0.0;
        }

        $groups = $product->optionGroups()->with('choices')->get()->keyBy('id');
        $sum = 0.0;

        foreach ($configuration as $groupId => $answer) {
            $group = $groups->get((int) $groupId);

            if ($group === null) {
                continue;
            }

            $sum += (float) $group->surcharge_gross;

            if (isset($answer['choice'])) {
                $choice = $group->choices->firstWhere('id', (int) $answer['choice']);
                $sum += $choice !== null ? (float) $choice->surcharge_gross : 0.0;
            }
        }

        return round($sum, 2);
    }

    /**
     * Konfiguracja opisana po ludzku — do koszyka, maila i arkusza produkcyjnego.
     *
     * Zwraca listę par „nazwa grupy → co wybrano". Etykiety bierzemy ze ŚWIEŻYCH
     * danych, więc zmiana nazwy grupy poprawia też koszyki już złożone; przy
     * pozycji zamówienia będzie inaczej (tam potrzebna jest migawka).
     *
     * @param  array<int, mixed>  $configuration
     * @return list<array{label: string, value: string}>
     */
    public static function describe(Product $product, array $configuration): array
    {
        if ($configuration === []) {
            return [];
        }

        $groups = $product->optionGroups()->with(['fields', 'choices'])->get()->keyBy('id');
        $out = [];

        foreach ($configuration as $groupId => $answer) {
            $group = $groups->get((int) $groupId);

            if ($group === null) {
                continue;
            }

            if (isset($answer['choice'])) {
                $choice = $group->choices->firstWhere('id', (int) $answer['choice']);

                if ($choice !== null) {
                    $out[] = ['label' => $group->name, 'value' => $choice->label];
                }

                continue;
            }

            foreach ($answer['fields'] ?? [] as $fieldId => $value) {
                $field = $group->fields->firstWhere('id', (int) $fieldId);

                if ($field !== null) {
                    $out[] = ['label' => $group->name.' — '.$field->label, 'value' => (string) $value];
                }
            }
        }

        return $out;
    }
}
