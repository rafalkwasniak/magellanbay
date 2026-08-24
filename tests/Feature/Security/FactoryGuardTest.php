<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;
use Tests\TestCase;

/**
 * DB_SECURITY.md, Warstwa 7 — fabryki modeli zablokowane na produkcji.
 *
 * Lekcja z 2026-08-16: `User::factory()->create()` wykonane ręcznie na
 * produkcyjnym połączeniu zostawiło w bazie konto sprzedawcy z losowym
 * nazwiskiem i domyślnym hasłem `password`. Przeżyło 8 dni.
 */
class FactoryGuardTest extends TestCase
{
    /**
     * Resolver nazw fabryk jest statyczny na klasie `Factory`, więc przeciek
     * z tego testu wywróciłby każdy następny test w tym samym procesie.
     * Sprzątamy zawsze — także gdy asercja rzuci.
     */
    protected function tearDown(): void
    {
        Factory::flushState();

        parent::tearDown();
    }

    public function test_factories_are_blocked_when_environment_is_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new AppServiceProvider($this->app))->boot();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fabryki modeli są zablokowane na produkcji');

        User::factory()->make();
    }

    public function test_factories_work_normally_outside_production(): void
    {
        // Bez podmiany środowiska: suita chodzi na `testing`, więc garda ma
        // się w ogóle nie zarejestrować. Gdyby się zarejestrowała, przewróciłby
        // się każdy inny test w projekcie — ten pilnuje, że tak nie jest.
        (new AppServiceProvider($this->app))->boot();

        $this->assertNotNull(User::factory()->make()->email);
    }
}
