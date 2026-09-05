<?php

namespace Database\Seeders;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\VatRate;
use App\Models\Shop;
use App\Models\User;
use App\Services\SlugService;
use App\Support\Mode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dane startowe wdrożenia u klienta: konto właściciela i jedyny rekord `shops`.
 *
 * To jest ta część roboty, która sprawia, że kolejny klient dedykowany kosztuje
 * godziny zamiast dni — dlatego jest seederem (procedurą do powtórzenia), a nie
 * zrzutem SQL (migawką jednej chwili). Parametry siedzą w `config/deployment.php`
 * i przychodzą z `.env`, więc następne wdrożenie nie dotyka tego pliku.
 *
 * PISANY RĘCZNIE, BEZ FABRYK — świadomie i nieodwołalnie. Fabryka wypełnia bazę
 * zmyślonymi danymi; tutaj baza jest produkcyjną bazą klienta. Incydent z
 * 2026-08-16 (konto sprzedawcy z hasłem `password`, przeżyło 8 dni) wziął się
 * dokładnie z pomylenia tych dwóch sytuacji. Warstwa 7 z DB_SECURITY.md blokuje
 * dziś fabryki na produkcji, ale zasada nie zależy od blokady.
 *
 * Uruchomienie: `php artisan db:seed --class=DeploymentSeeder --force`
 *
 * @see docs_mod/06-dane-startowe.md — dlaczego seeder, a nie zrzut produkcji
 */
class DeploymentSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardMode();
        $this->guardEmptyDatabase();

        $config = config('deployment');

        $email = trim((string) ($config['owner']['email'] ?? ''));
        $shopName = trim((string) ($config['shop']['name'] ?? ''));

        $this->guardRequired($email, $shopName);

        $shop = DB::transaction(fn () => $this->createOwnerAndShop($config, $email, $shopName));

        $this->report($shop);
    }

    /**
     * Ten seeder zakłada sklep na pakiecie `dedicated` — bez limitów i bez
     * wygasania. Puszczony na Kramio przez pomyłkę utworzyłby sprzedawcę
     * z dożywotnim darmowym Pawilonem, i to bez śladu w historii pakietów.
     */
    private function guardMode(): void
    {
        if (! Mode::dedicated()) {
            throw new RuntimeException(
                'DeploymentSeeder działa wyłącznie w trybie dedykowanym. '
                .'Ustaw SHOP_MODE=dedicated w .env albo — jeśli to instalacja Kramio — '
                .'nie uruchamiaj go w ogóle.'
            );
        }
    }

    /**
     * Drugie uruchomienie utworzyłoby DRUGI sklep, a `ResolveShop` w trybie
     * dedykowanym bierze `Shop::query()->first()`. Nadmiarowy rekord byłby więc
     * niewidoczny na stronie i tym trudniejszy do zauważenia — do momentu, w
     * którym ktoś skasuje pierwszy i sklep „sam z siebie" zmieni zawartość.
     */
    private function guardEmptyDatabase(): void
    {
        if (Shop::query()->exists()) {
            throw new RuntimeException(
                'W bazie jest już sklep — seeder wdrożeniowy nie ma tu nic do zrobienia. '
                .'Jeśli chcesz zacząć od zera, postaw bazę od nowa; jeśli chcesz poprawić '
                .'dane sklepu, zrób to w panelu, bo to on jest źródłem prawdy.'
            );
        }
    }

    /**
     * Dwie wartości, których nie da się sensownie zgadnąć ani zostawić pustych:
     * bez adresu właściciel nie ustawi hasła, bez nazwy sklep nie ma się jak
     * przedstawić. Reszta pól może poczekać na panel.
     */
    private function guardRequired(string $email, string $shopName): void
    {
        $missing = [];

        if ($email === '') {
            $missing[] = 'DEPLOY_OWNER_EMAIL';
        }

        if ($shopName === '') {
            $missing[] = 'DEPLOY_SHOP_NAME (albo APP_NAME)';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Brak wymaganych wartości w .env: '.implode(', ', $missing).'. '
                .'Uzupełnij je i uruchom seeder ponownie.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function createOwnerAndShop(array $config, string $email, string $shopName): Shop
    {
        $user = new User;
        $user->name = (string) $config['owner']['name'];
        $user->surname = (string) $config['owner']['surname'];
        $user->email = $email;
        $user->phone = (string) $config['owner']['phone'];

        // Hasło losowe i nie do odgadnięcia — właściciel ustawia własne z linku
        // aktywacyjnego. Dokładnie jak przy rejestracji w Kramio; różnica jest
        // tylko taka, że tam sprzedawca wpisuje adres sam, a tu wpisujemy go my.
        $user->password = Str::password(32);

        // Rola nie jest mass-assignable — ustawiamy jawnie.
        $user->role = UserRole::Seller;
        $user->save();

        $shop = $user->shop()->create([
            'name' => $shopName,
            'slug' => $this->slug($config, $shopName),
            'status' => ShopStatus::Draft,
            'contact_email' => (string) $config['shop']['contact_email'],
            'contact_phone' => (string) $config['shop']['contact_phone'],
            'company_name' => (string) $config['company']['company_name'],
            'nip' => (string) $config['company']['nip'],
            'country' => (string) $config['company']['country'],
            'province' => (string) $config['company']['province'],
            'city' => (string) $config['company']['city'],
            'postal_code' => (string) $config['company']['postal_code'],
            'street' => (string) $config['company']['street'],
            'building_number' => (string) $config['company']['building_number'],
            'apartment_number' => (string) $config['company']['apartment_number'],
            'default_vat_rate' => VatRate::from((string) $config['sales']['default_vat_rate']),
        ]);

        /*
         * KOLEJNOŚĆ MA ZNACZENIE: `comped` przed `assignPackage()`.
         *
         * `assignPackage()` kończy się `save()`, więc ustawiona wcześniej flaga
         * zapisuje się razem z pakietem — jednym zapisem, bez okna, w którym
         * sklep ma już pakiet płatny, a jeszcze nie ma dostępu gratisowego.
         *
         * `comped` to cały mechanizm braku abonamentu: `subscriptionActive()`
         * zaczyna od tej flagi i zwraca `true`, nie patrząc na datę. Bez niej
         * `entitlement()` zjechałby na pakiet darmowy i klient trafiłby na
         * limity, za które zapłacił, żeby ich nie mieć.
         */
        $shop->comped = true;
        $shop->assignPackage((string) $config['package']);

        // Sklep zostaje SZKICEM. Publikacja to decyzja właściciela — po wgraniu
        // produktów i uzupełnieniu dokumentów, nie w chwili zakładania bazy.
        // Do tego czasu storefront jest widoczny tylko dla zalogowanego
        // właściciela (podgląd szkicu).

        return $shop;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function slug(array $config, string $shopName): string
    {
        $slug = trim((string) $config['shop']['slug']);

        return $slug !== ''
            ? $slug
            : app(SlugService::class)->make($shopName);
    }

    /**
     * Link aktywacyjny WYPISUJEMY, zamiast wysyłać mailem — z dwóch powodów.
     *
     * 1. Seeder chodzi zaraz po `migrate`, gdy SMTP bywa nieskonfigurowany, a
     *    crona (który jako jedyny opróżnia outbox) jeszcze nie ma. Mail wpadłby
     *    do kolejki i tam został, a wdrażający byłby przekonany, że poszedł.
     * 2. Treść maila aktywacyjnego mówi dziś językiem platformy („dziękujemy za
     *    rejestrację", „postawisz swój sklep w kilka minut"). W sklepie
     *    dedykowanym nikt się nie rejestrował. To pozycja kroku 4 (marka) —
     *    do czasu jej poprawienia nie wysyłamy tego klientowi.
     *
     * Token żyje 24 h. Gdy wygaśnie, właściciel bierze nowy przez „nie pamiętam
     * hasła" — nie trzeba niczego seedować ponownie.
     */
    private function report(Shop $shop): void
    {
        $owner = $shop->owner;

        $url = route('activation.show', [
            'token' => Password::broker('activation')->createToken($owner),
            'email' => $owner->email,
        ]);

        $this->command?->newLine();
        $this->command?->info('Sklep założony: '.$shop->name);
        $this->command?->line('  Właściciel:      '.$owner->email);
        $this->command?->line('  Pakiet:          '.$shop->package.' (comped: bez wygasania)');
        $this->command?->line('  Limit produktów: '.$shop->entitlement('max_products'));
        $this->command?->line('  Status sklepu:   szkic — publikuje właściciel z panelu');
        $this->command?->newLine();
        $this->command?->warn('Link do ustawienia hasła (ważny 24 h) — przekaż właścicielowi:');
        $this->command?->line('  '.$url);
        $this->command?->newLine();
    }
}
