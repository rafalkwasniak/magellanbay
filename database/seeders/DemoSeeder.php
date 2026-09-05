<?php

namespace Database\Seeders;

use App\Enums\DeliveryMethod;
use App\Enums\OptionGroupKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleUnit;
use App\Enums\VatRate;
use App\Models\Product;
use App\Models\Shop;
use App\Services\SlugService;
use App\Support\ProductConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dane POKAZOWE — wyłącznie środowisko robocze. NIGDY u klienta.
 *
 * Po to, żeby dało się pracować nad wyglądem i pokazać klientowi działający
 * sklep, zanim wgra własny katalog. Panel z pustą listą produktów i pustą listą
 * zamówień nie mówi nic o tym, jak sklep wygląda w użyciu.
 *
 * Uruchomienie: `php artisan db:seed --class=DemoSeeder`
 *
 * ==> PRZED PRZEKAZANIEM KLIENTOWI TE DANE MUSZĄ ZNIKNĄĆ. <==
 *
 * Najpewniej przez postawienie bazy od nowa (`migrate:fresh` + DeploymentSeeder),
 * nie przez kasowanie rekord po rekordzie — bo po produktach zostają jeszcze
 * tagi, zdjęcia, pozycje zamówień i numeracja zamówień sklepu, i to właśnie one
 * są tym, o czym się zapomina.
 *
 * BEZ FABRYK, mimo że to tylko dane pokazowe. Nie z ostrożności — z pożytku:
 * `OrderFactory` wystawia zamówienia z zerowymi kwotami, a produkty z losowymi
 * nazwami. Demo ma pokazywać sklep z magnesami podróżniczymi, a nie „Voluptas
 * Quia Sit" za 0,00 zł.
 *
 * @see docs_mod/06-dane-startowe.md
 */
class DemoSeeder extends Seeder
{
    /**
     * Produkty pokazowe: [nazwa, cena brutto, opis, personalizowany].
     *
     * `withdrawal_excluded` na produktach personalizowanych NIE jest ozdobnikiem
     * demo — magnes z czyimś imieniem jest rzeczą wykonaną na indywidualne
     * zamówienie i art. 38 pkt 3 u.p.k. wyłącza go z prawa odstąpienia. To
     * jedyna sytuacja, w której wolno tę flagę podnieść, i tak ma wyglądać
     * przyszły katalog Magellana.
     *
     * @var list<array{0: string, 1: float, 2: string, 3: bool}>
     */
    private const PRODUCTS = [
        ['Magnes Gdańsk — Żuraw', 24.90, 'Widok na Żuraw i Motławę o poranku. Magnes z blachy emaliowanej, 70 × 50 mm.', false],
        ['Magnes Kraków — Wawel', 24.90, 'Wzgórze wawelskie od strony Wisły. Magnes z blachy emaliowanej, 70 × 50 mm.', false],
        ['Magnes Zakopane — Giewont', 24.90, 'Giewont w zimowej odsłonie. Magnes z blachy emaliowanej, 70 × 50 mm.', false],
        ['Magnes Wrocław — Rynek', 24.90, 'Kamienice wrocławskiego Rynku. Magnes z blachy emaliowanej, 70 × 50 mm.', false],
        ['Magnes Bieszczady — Połonina', 26.90, 'Połonina Wetlińska o zachodzie słońca. Magnes z blachy emaliowanej, 70 × 50 mm.', false],
        ['Magnes Mazury — Śniardwy', 26.90, 'Żagle na Śniardwach. Magnes z blachy emaliowanej, 70 × 50 mm.', false],
        ['Magnes z imieniem — seria górska', 34.90, 'Magnes z Twoim imieniem na tle panoramy Tatr. Nadruk wykonujemy indywidualnie.', true],
        ['Magnes z imieniem — seria morska', 34.90, 'Magnes z Twoim imieniem na tle bałtyckiej plaży. Nadruk wykonujemy indywidualnie.', true],
        ['Magnes pamiątkowy z datą', 39.90, 'Magnes z datą i miejscem — na pamiątkę wyjazdu, ślubu albo pierwszego szczytu.', true],
        ['Magnes z grawerem — stal szlifowana', 49.90, 'Magnes ze stali szlifowanej z grawerem na rewersie. Grawerujemy tekst lub grafikę.', true],
        ['Zestaw startowy — 6 magnesów', 129.00, 'Sześć magnesów z serii miejskiej w kartonowym etui. Dobra pamiątka na prezent.', false],
        ['Ramka magnetyczna na 12 magnesów', 89.00, 'Stalowa ramka do powieszenia na ścianie. Mieści dwanaście magnesów serii 70 × 50 mm.', false],
    ];

    /**
     * @var list<string>
     */
    private const TAGS = ['miasta', 'góry', 'morze', 'personalizowane', 'na prezent'];

    public function run(): void
    {
        $this->guardProduction();

        $shop = Shop::query()->first();

        if ($shop === null) {
            throw new RuntimeException(
                'Nie ma sklepu, do którego można dopiąć dane pokazowe. '
                .'Uruchom najpierw DeploymentSeeder.'
            );
        }

        DB::transaction(function () use ($shop): void {
            $products = $this->createProducts($shop);
            $this->createTags($shop, $products);
            $config = $this->createPersonalisation($shop, $products);
            $this->createOrders($shop, $products, $config);
        });

        $this->command?->newLine();
        $this->command?->info('Dane pokazowe dodane do sklepu: '.$shop->name);
        $this->command?->line('  Produkty:   '.count(self::PRODUCTS).' (bez zdjęć — te wgrywa się w panelu)');
        $this->command?->line('  Tagi:       '.count(self::TAGS));
        $this->command?->line('  Zamówienia: 3 (jedno z personalizacją, jedno z licencjami)');
        $this->command?->line('  Grupy opcji: 4, licencjodawcy: 2');
        $this->command?->newLine();
        $this->command?->warn('PAMIĘTAJ: te dane muszą zniknąć przed przekazaniem sklepu klientowi.');
        $this->command?->newLine();
    }

    /**
     * Garda jest tu mimo że seeder z definicji chodzi u nas: „u nas" jest cechą
     * intencji, a nie środowiska, i dokładnie takie założenie kosztowało nas już
     * raz konto z hasłem `password` na produkcji (DB_SECURITY.md, Warstwa 7).
     */
    private function guardProduction(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'DemoSeeder nie działa na produkcji. To dane pokazowe — w sklepie '
                .'klienta nie mają czego szukać.'
            );
        }
    }

    /**
     * @return list<Product>
     */
    private function createProducts(Shop $shop): array
    {
        $slugs = app(SlugService::class);
        $created = [];

        foreach (self::PRODUCTS as $index => [$name, $price, $description, $personalised]) {
            $created[] = $shop->products()->create([
                'name' => $name,
                'slug' => $slugs->make($name),
                'description' => '<div>'.$description.'</div>',
                'price_gross' => $price,
                'vat_rate' => VatRate::R23,
                'sale_unit' => SaleUnit::Piece,
                'track_stock' => false,
                'is_active' => true,
                // Sufit promowanych na głównej to `shop.homepage_promoted_limit`;
                // przekroczenie go tutaj dałoby demo niezgodne z tym, co panel
                // w ogóle pozwala ustawić.
                'show_on_homepage' => $index < 3,
                'withdrawal_excluded' => $personalised,
            ]);
        }

        return $created;
    }

    /**
     * Personalizacja i licencjodawcy — dokładnie ten układ, który opisał klient.
     *
     * Awers: logotyp organizatora (opłata licencyjna, bez kosztu wykonania).
     * Rewers: grawer — grafika ALBO własny tekst, nigdy oba. Koszt wykonania
     * siedzi na grupie, opłata licencyjna przy konkretnej grafice.
     *
     * Dwie grafiki tego samego partnera są tu CELOWO: dzięki nim demo pokazuje
     * regułę „nie sumujemy, liczy się wyższa" na żywym zamówieniu, a nie tylko
     * w teście.
     *
     * @param  list<Product>  $products
     * @return array<string, mixed>
     */
    private function createPersonalisation(Shop $shop, array $products): array
    {
        $bieg = $shop->licensors()->create([
            'name' => 'Bieg Gdański',
            'contact_email' => 'licencje@bieggdanski.example',
            'agreement_reference' => 'UM/2026/014',
        ]);

        $pzla = $shop->licensors()->create([
            'name' => 'Polski Związek Lekkiej Atletyki',
            'contact_email' => 'znak@pzla.example',
            'agreement_reference' => 'UM/2026/031',
        ]);

        // Awers — logotyp z licencją, bez kosztu wykonania.
        $logotyp = $shop->optionGroups()->create([
            'name' => 'Logotyp na awersie',
            'kind' => OptionGroupKind::Choice,
            'hint' => 'Nieobowiązkowy. Za użycie znaku organizatora doliczamy opłatę licencyjną.',
            'position' => 0,
        ]);
        $logoBiegu = $logotyp->choices()->create([
            'label' => 'Bieg Gdański 2026', 'licensor_id' => $bieg->id, 'licence_fee_gross' => 5.00, 'position' => 0,
        ]);
        $logotyp->choices()->create([
            'label' => 'PZLA', 'licensor_id' => $pzla->id, 'licence_fee_gross' => 7.00, 'position' => 1,
        ]);

        // Rewers, wariant graficzny — koszt wykonania na grupie.
        $grawerGrafika = $shop->optionGroups()->create([
            'name' => 'Grawer — grafika',
            'kind' => OptionGroupKind::Choice,
            'hint' => 'Wybierz grafikę z naszej biblioteki.',
            'surcharge_gross' => 15.00,
            'position' => 1,
        ]);
        $trasaBiegu = $grawerGrafika->choices()->create([
            'label' => 'Trasa Biegu Gdańskiego', 'licensor_id' => $bieg->id, 'licence_fee_gross' => 8.00, 'position' => 0,
        ]);
        $grawerGrafika->choices()->create([
            'label' => 'Kompas', 'surcharge_gross' => 3.00, 'position' => 1,
        ]);

        // Rewers, wariant tekstowy — wyklucza się z graficznym.
        $grawerTekst = $shop->optionGroups()->create([
            'name' => 'Grawer — własny tekst',
            'kind' => OptionGroupKind::Text,
            'hint' => 'Zamiast grafiki możemy wygrawerować Twój tekst.',
            'surcharge_gross' => 15.00,
            'excludes_group_id' => $grawerGrafika->id,
            'position' => 2,
        ]);
        $grawerTekst->fields()->create(['label' => 'Tekst', 'max_length' => 24, 'position' => 0]);
        $grawerTekst->fields()->create(['label' => 'Data', 'max_length' => 10, 'required' => false, 'position' => 1]);

        // Nadruk imienia — najprostsza formatka, ta „pod kubek z imieniem".
        $nadruk = $shop->optionGroups()->create([
            'name' => 'Nadruk imienia',
            'kind' => OptionGroupKind::Text,
            'hint' => 'Wpisz imię, które nadrukujemy na froncie.',
            'surcharge_gross' => 10.00,
            'required' => true,
            'position' => 3,
        ]);
        $imie = $nadruk->fields()->create(['label' => 'Imię', 'max_length' => 12, 'position' => 0]);

        // Produkty personalizowane (indeksy z self::PRODUCTS, flaga `true`).
        foreach ([6, 7, 8] as $index) {
            $products[$index]->optionGroups()->attach($nadruk);
        }
        $products[9]->optionGroups()->attach([$logotyp->id, $grawerGrafika->id, $grawerTekst->id]);

        return [
            'nadruk' => $nadruk,
            'imie' => $imie,
            'logotyp' => $logotyp,
            'logoBiegu' => $logoBiegu,
            'grawerGrafika' => $grawerGrafika,
            'trasaBiegu' => $trasaBiegu,
        ];
    }

    /**
     * @param  list<Product>  $products
     */
    private function createTags(Shop $shop, array $products): void
    {
        $slugs = app(SlugService::class);
        $tags = [];

        foreach (self::TAGS as $name) {
            $tags[$name] = $shop->tags()->create([
                'name' => $name,
                'slug' => $slugs->make($name),
            ]);
        }

        // Przypisania odzwierciedlają katalog, a nie losowanie — chodzi o to, by
        // chmura tagów na storefroncie wyglądała jak prawdziwa, z różnymi
        // liczebnościami, a nie jak równo rozdane pięć kupek.
        $map = [
            0 => ['miasta'], 1 => ['miasta'], 3 => ['miasta'],
            2 => ['góry'], 4 => ['góry'],
            5 => ['morze'],
            6 => ['góry', 'personalizowane'],
            7 => ['morze', 'personalizowane'],
            8 => ['personalizowane', 'na prezent'],
            9 => ['personalizowane', 'na prezent'],
            10 => ['na prezent'],
            11 => ['na prezent'],
        ];

        foreach ($map as $index => $names) {
            $products[$index]->tags()->attach(
                array_map(fn (string $name): int => $tags[$name]->id, $names)
            );
        }
    }

    /**
     * Dwa zamówienia w różnych statusach, żeby panel i kafelek „nowe zamówienia"
     * miały co pokazać.
     *
     * Kwoty liczymy, zamiast wpisywać: `total_net` i `total_vat` muszą się
     * zgadzać z `total_gross`, inaczej demo pokazuje sumy, których nie da się
     * wystawić na fakturze — a to najgorszy rodzaj danych pokazowych, bo wygląda
     * poprawnie i uczy złego.
     *
     * @param  list<Product>  $products
     */
    private function createOrders(Shop $shop, array $products, array $config): void
    {
        /*
         * Pierwsze zamówienie niesie PERSONALIZACJĘ, żeby panel pokazywał to,
         * co sprzedawca naprawdę zobaczy: „2 × Magnes" bez imienia jest
         * zamówieniem, którego nie da się wykonać.
         *
         * Uwaga z treści zamówienia zniknęła — imię stoi teraz w polu, a nie
         * w prośbie wpisanej przez klienta w komentarzu. O to w tym module chodzi.
         */
        $this->createOrder($shop, OrderStatus::New, 'Anna', 'Wiśniewska', [
            [$products[0], 2, []],
            [$products[6], 1, [
                $config['nadruk']->id => ['fields' => [$config['imie']->id => 'Zosia']],
            ]],
        ], null);

        $this->createOrder($shop, OrderStatus::Completed, 'Marek', 'Zieliński', [
            [$products[10], 1, []],
        ], null);

        /*
         * Trzecie zamówienie pokazuje REGUŁĘ LICENCJI na żywym przykładzie:
         * logotyp Biegu Gdańskiego na awersie (5 zł) i jego grafika na rewersie
         * (8 zł). Ten sam partner dwa razy, więc naliczamy 8 zł, nie 13 —
         * i widać to w rozbiciu ceny pozycji.
         */
        $this->createOrder($shop, OrderStatus::Paid, 'Piotr', 'Lewandowski', [
            [$products[9], 1, [
                $config['logotyp']->id => ['choice' => $config['logoBiegu']->id],
                $config['grawerGrafika']->id => ['choice' => $config['trasaBiegu']->id],
            ]],
        ], null);
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function createOrder(
        Shop $shop,
        OrderStatus $status,
        string $name,
        string $surname,
        array $lines,
        ?string $note,
    ): void {
        $deliveryCost = 15.99;

        /*
         * Ceny jednostkowe liczymy z ROZBICIA, tą samą metodą co OrderService.
         * Wpisane tu osobno rozjechałyby się z aplikacją przy pierwszej zmianie
         * regul — a demo, ktore pokazuje inne kwoty niz sklep, jest gorsze niz
         * brak demo.
         */
        $prices = [];
        $itemsTotal = 0.0;

        foreach ($lines as $i => [$product, $quantity, $config]) {
            $breakdown = ProductConfiguration::breakdown($product, $config);
            $unit = round(array_sum(array_column($breakdown, 'amount')), 2);

            $prices[$i] = ['unit' => $unit, 'breakdown' => $breakdown];
            $itemsTotal += $unit * $quantity;
        }

        $gross = round($itemsTotal + $deliveryCost, 2);

        // Cały koszyk demo jest w jednej stawce 23%, więc rozbicie jest proste.
        // Przy mieszanych stawkach trzeba liczyć per stawka — o czym warto
        // pamiętać, gdyby ktoś dorzucał tu produkt z inną stawką.
        $net = round($gross / 1.23, 2);

        $order = $shop->orders()->create([
            'number' => $shop->allocateOrderNumber(),
            'status' => $status,
            'buyer_name' => $name,
            'buyer_surname' => $surname,
            'buyer_email' => mb_strtolower($name).'.'.mb_strtolower($surname).'@example.com',
            'buyer_phone' => '600100200',
            'is_company' => false,
            'ship_street' => 'Kwiatowa',
            'ship_building_number' => '12',
            'ship_postal_code' => '80-001',
            'ship_city' => 'Gdańsk',
            'delivery_method' => DeliveryMethod::Courier,
            'delivery_cost' => $deliveryCost,
            'payment_method' => PaymentMethod::BankTransfer,
            'items_total' => round($itemsTotal, 2),
            'total_net' => $net,
            'total_vat' => round($gross - $net, 2),
            'total_gross' => $gross,
            'note' => $note,
        ]);

        foreach ($lines as $i => [$product, $quantity, $config]) {
            $unit = $prices[$i]['unit'];

            $item = $order->items()->create([
                'product_id' => $product->id,
                'name' => $product->name,
                'personalisation' => ProductConfiguration::describe($product, $config) ?: null,
                'configuration' => $config ?: null,
                'unit_price_gross' => $unit,
                'personalisation_surcharge_gross' => ProductConfiguration::surcharge($product, $config),
                'vat_rate' => $product->vat_rate,
                'quantity' => $quantity,
                'sale_unit' => $product->sale_unit,
                'line_total_gross' => round($unit * $quantity, 2),
            ]);

            foreach ($prices[$i]['breakdown'] as $position => $component) {
                $item->components()->create([
                    'kind' => $component['kind'],
                    'label' => $component['label'],
                    'licensor_id' => $component['licensor_id'],
                    'licensor_name' => $component['licensor_name'],
                    'unit_amount_gross' => $component['amount'],
                    'position' => $position,
                ]);
            }
        }
    }
}
