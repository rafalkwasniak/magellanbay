<?php

namespace Tests\Feature;

use App\Support\Vocative;
use Tests\TestCase;

class VocativeTest extends TestCase
{
    public function test_popular_female_names_get_vocative(): void
    {
        $this->assertSame('Anno', Vocative::of('Anna'));
        $this->assertSame('Katarzyno', Vocative::of('Katarzyna'));
        $this->assertSame('Mario', Vocative::of('Maria'));
        $this->assertSame('Magdaleno', Vocative::of('Magdalena'));
    }

    public function test_female_diminutives_take_u_not_o(): void
    {
        $this->assertSame('Kasiu', Vocative::of('Kasia'));
        $this->assertSame('Basiu', Vocative::of('Basia'));
        $this->assertSame('Zosiu', Vocative::of('Zosia'));
        $this->assertSame('Aniu', Vocative::of('Ania'));
    }

    /**
     * Sedno tego, dlaczego jest tu słownik, a nie reguła: identyczna końcówka
     * -la, różny wołacz. Zdrobnienie idzie na -u, pełne imię na -o.
     */
    public function test_identical_endings_can_take_different_vocatives(): void
    {
        $this->assertSame('Olu', Vocative::of('Ola'));
        $this->assertSame('Kamilo', Vocative::of('Kamila'));

        $this->assertSame('Elu', Vocative::of('Ela'));
        $this->assertSame('Izabelo', Vocative::of('Izabela'));
    }

    public function test_male_names_with_fleeting_e(): void
    {
        $this->assertSame('Marku', Vocative::of('Marek'));
        $this->assertSame('Pawle', Vocative::of('Paweł'));
        $this->assertSame('Aleksandrze', Vocative::of('Aleksander'));
        $this->assertSame('Kacprze', Vocative::of('Kacper'));
    }

    public function test_male_names_with_palatalisation(): void
    {
        $this->assertSame('Piotrze', Vocative::of('Piotr'));
        $this->assertSame('Robercie', Vocative::of('Robert'));
        $this->assertSame('Dawidzie', Vocative::of('Dawid'));
        $this->assertSame('Michale', Vocative::of('Michał'));
        $this->assertSame('Maćku', Vocative::of('Maciek'));
    }

    /**
     * Imiona przymiotnikowe mają wołacz równy mianownikowi — „Cześć Jerzy" jest
     * poprawne, a nie „nie znaleziono".
     */
    public function test_adjectival_names_stay_unchanged(): void
    {
        $this->assertSame('Jerzy', Vocative::of('Jerzy'));
        $this->assertSame('Antoni', Vocative::of('Antoni'));
        $this->assertSame('Cezary', Vocative::of('Cezary'));
        $this->assertSame('Ignacy', Vocative::of('Ignacy'));
    }

    /**
     * Nieznane imię NIE jest zgadywane — wraca w mianowniku. „Cześć Kevin" jest
     * w porządku, „Cześć Kevino" ośmieszałoby sklep.
     */
    public function test_unknown_names_fall_back_to_nominative(): void
    {
        $this->assertSame('Kevin', Vocative::of('Kevin'));
        $this->assertSame('Nguyen', Vocative::of('Nguyen'));
        $this->assertSame('Dmytro', Vocative::of('Dmytro'));
    }

    public function test_only_the_first_token_is_used(): void
    {
        $this->assertSame('Anno', Vocative::of('Anna Kowalska'));
        $this->assertSame('Piotrze', Vocative::of('Piotr  Nowak'));
        $this->assertSame('Kevin', Vocative::of('Kevin Smith'));
    }

    public function test_shouting_input_is_calmed_down(): void
    {
        $this->assertSame('Anno', Vocative::of('ANNA'));
        $this->assertSame('Kevin', Vocative::of('KEVIN'));
    }

    public function test_lowercase_input_still_matches(): void
    {
        $this->assertSame('Anno', Vocative::of('anna'));
        $this->assertSame('Łukaszu', Vocative::of('łukasz'));
    }

    public function test_non_names_yield_null(): void
    {
        $this->assertNull(Vocative::of(null));
        $this->assertNull(Vocative::of(''));
        $this->assertNull(Vocative::of('   '));
        $this->assertNull(Vocative::of('FIRMA XYZ SP. Z O.O.'));
        $this->assertNull(Vocative::of('123'));
        $this->assertNull(Vocative::of('A'));
    }

    public function test_hyphenated_names_are_accepted(): void
    {
        $this->assertSame('Anna-Maria', Vocative::of('Anna-Maria'));
    }

    public function test_greeting_line_for_mails(): void
    {
        $this->assertSame('Cześć Anno,', Vocative::greeting('Anna'));
        $this->assertSame('Cześć Kevin,', Vocative::greeting('Kevin'));
        $this->assertSame('Dzień dobry,', Vocative::greeting(null));
        $this->assertSame('Dzień dobry,', Vocative::greeting('FIRMA XYZ SP. Z O.O.'));
    }

    /**
     * Higiena słownika — pilnuje przyszłych dopisków: klucz musi być lowercase
     * (inaczej nigdy nie trafi), a wartość zaczynać wielką literą (idzie prosto
     * do maila).
     */
    public function test_dictionary_entries_are_well_formed(): void
    {
        $dictionary = config('vocative');

        $this->assertNotEmpty($dictionary);

        foreach ($dictionary as $nominative => $vocative) {
            $this->assertSame(
                mb_strtolower($nominative),
                $nominative,
                "Klucz {$nominative} musi być zapisany małymi literami."
            );

            $this->assertMatchesRegularExpression(
                '/^\p{Lu}/u',
                $vocative,
                "Wołacz {$vocative} musi zaczynać się wielką literą."
            );
        }
    }
}
