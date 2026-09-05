<?php

namespace Database\Seeders;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\VatRate;
use App\Models\Shop;
use App\Models\User;
use App\Services\ActivationMailer;
use App\Services\SlugService;
use App\Support\Mode;
use App\Support\SellerPrivacy;
use App\Support\SellerTerms;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
            'template' => $this->template($config),
            'theme' => ['palette' => (string) $config['appearance']['palette']],
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

        $this->fillLegalPages($shop, $config);

        // Sklep zostaje SZKICEM. Publikacja to decyzja właściciela — po wgraniu
        // produktów i uzupełnieniu dokumentów, nie w chwili zakładania bazy.
        // Do tego czasu storefront jest widoczny tylko dla zalogowanego
        // właściciela (podgląd szkicu).

        return $shop;
    }

    /**
     * Wypełnia strony systemowe wzorami regulaminu i polityki prywatności.
     *
     * DLACZEGO OD RAZU, A NIE ZOSTAWIĆ ZAŚLEPKI: strony systemowe są zawsze
     * OPUBLIKOWANE, a odnośniki do nich stoją w nagłówku i stopce od pierwszego
     * dnia. Wybór nie brzmi więc „szkic czy publikacja", tylko „co klient
     * publikuje, zanim usiądzie do dokumentów" — zaślepka „w przygotowaniu"
     * czy wzór z widocznymi lukami. Wzór jest lepszy w obie strony: klientowi
     * pokazuje, co ma uzupełnić, a jego kupującym mówi choć część prawdy.
     *
     * @param  array<string, mixed>  $config
     */
    private function fillLegalPages(Shop $shop, array $config): void
    {
        $dane = $this->legalAnswers($config);

        $this->fillPage($shop, config('pages.regulamin.slug'), SellerTerms::render($shop, $dane), SellerTerms::VERSION);
        $this->fillPage($shop, config('pages.privacy.slug'), SellerPrivacy::render($shop, $dane), SellerPrivacy::VERSION);
    }

    private function fillPage(Shop $shop, string $slug, string $content, int $version): void
    {
        $page = $shop->pages()->where('slug', $slug)->first();

        // Strona powstaje w ShopObserver. Gdyby jej nie było (np. polityka
        // w trybie SaaS), po prostu nie ma czego wypełniać.
        $page?->forceFill([
            'content' => $content,
            'terms_template_version' => $version,
        ])->save();
    }

    /**
     * Odpowiedzi do wzorów: wartość z `.env` albo WIDOCZNA LUKA.
     *
     * Luki wypisujemy jako [WIELKIE_LITERY_W_NAWIASACH], bo dokument powstaje
     * tu ZA klienta, zanim poda dane firmowe — a szukanie niewypełnionych
     * miejsc w kilkunastu tysiącach znaków bez oznaczenia jest polowaniem
     * na igłę. Nawiasy kwadratowe, nie klamry: klamra to składnia Blade.
     *
     * NIP-a też oznaczamy luką, choć w kreatorze pusty NIP znaczy „działalność
     * nierejestrowana". Różnica jest w tym, KTO odpowiedział: tam człowiek
     * świadomie zostawił pole puste, tutaj po prostu nikt go nie wypełnił.
     * Napisanie w cudzym regulaminie „działalność nierejestrowana" na podstawie
     * pustej zmiennej środowiskowej byłoby zmyśleniem faktu prawnego.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function legalAnswers(array $config): array
    {
        $albo = function (string $wartosc, string $etykieta): string {
            return trim($wartosc) !== '' ? trim($wartosc) : '['.$etykieta.']';
        };

        $adres = $albo(
            trim((string) $config['company']['street'].' '
                .$config['company']['building_number'].', '
                .$config['company']['postal_code'].' '
                .$config['company']['city'], ' ,'),
            'ADRES_SIEDZIBY'
        );

        return [
            'seller_name' => $albo((string) $config['company']['company_name'], 'NAZWA_SPRZEDAWCY'),
            'nip' => $albo((string) $config['company']['nip'], 'NIP'),
            'address' => $adres,
            'email' => $albo((string) $config['shop']['contact_email'], 'ADRES_EMAIL'),
            'phone' => $albo((string) $config['shop']['contact_phone'], 'TELEFON'),
            'return_address' => $adres,
            'shipping_days' => '[LICZBA_DNI]',
            'withdrawal_exclusions' => '',
        ];
    }

    /**
     * Szablon wyglądu — z twardym sprawdzeniem, że w ogóle istnieje.
     *
     * Literówka w `DEPLOY_TEMPLATE` nie wywaliłaby niczego: `Shop::themeTokens()`
     * spadłby na szablon domyślny i klient dostałby wygląd rodziny Kramio,
     * przekonany, że tak ma być. Lepiej zatrzymać wdrożenie na jednym czytelnym
     * błędzie niż oddać sklep w cudzych barwach.
     *
     * @param  array<string, mixed>  $config
     */
    private function template(array $config): string
    {
        $slug = (string) $config['appearance']['template'];

        if (config("themes.templates.{$slug}") === null) {
            throw new RuntimeException(
                "Nieznany szablon wygladu: {$slug}. Sprawdz DEPLOY_TEMPLATE — "
                .'dostepne sa klucze z config/themes.php.'
            );
        }

        return $slug;
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
     * Mail aktywacyjny do kolejki ORAZ link wypisany na konsolę.
     *
     * Jedno i drugie, bo każde z osobna zawodzi w innym momencie. Seeder chodzi
     * zaraz po `migrate`: SMTP bywa jeszcze nieskonfigurowany, a crona — który
     * jako jedyny opróżnia outbox — zwykle nie ma. Sam mail utknąłby w kolejce,
     * a wdrażający byłby przekonany, że poszedł. Sam wydruk z kolei ginie, gdy
     * sklep stawia ktoś inny niż właściciel.
     *
     * Link musi wyjść Z MAILERA, nie powstać tutaj obok: broker `activation`
     * kasuje poprzedni token przy tworzeniu nowego, więc wygenerowanie drugiego
     * unieważniłoby ten wysłany w mailu — i właściciel dostałby martwy link.
     *
     * Token żyje 24 h. Gdy wygaśnie, właściciel bierze nowy przez „nie pamiętam
     * hasła" — nie trzeba niczego seedować ponownie.
     */
    private function report(Shop $shop): void
    {
        $owner = $shop->owner;

        $url = app(ActivationMailer::class)->send($owner);

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
