<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * PUSTY — i tak ma zostać.
 *
 * Szkielet Laravela tworzy tu konto `test@example.com` z domyślnym hasłem
 * `password`. W tym projekcie nie ma dla niego zastosowania: dane produkcyjne
 * powstają przez rejestrację, a testy budują własne przez fabryki na sqlite
 * `:memory:`. Zostawiony szkielet byłby tylko nabitą strzelbą — jedno
 * `db:seed --force` na produkcji i mamy w bazie konto sprzedawcy ze znanym
 * hasłem (dokładnie taki śmieć sprzątaliśmy 2026-08-24).
 *
 * Fabryki są dodatkowo zablokowane na produkcji — patrz DB_SECURITY.md,
 * Warstwa 7. Gdyby kiedyś pojawiła się potrzeba seedowania danych startowych
 * (słowniki, stawki), pisz je jawnie, bez fabryk.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        //
    }
}
