<?php

namespace App\Models;

use App\Enums\IntegrationType;
use App\Enums\ShopStatus;
use App\Enums\VatRate;
use App\Support\Color;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sklep jednego sprzedawcy. `slug` = etykieta subdomeny ({slug}.{central_domain}).
 * `domain` = opcjonalna dedykowana domena (np. mojsklep.pl). `owner_id` nie jest
 * mass-assignable — sklep tworzymy przez relację usera.
 */
#[Fillable([
    'name', 'slug', 'domain', 'status', 'description', 'company_name', 'nip', 'logo_path',
    'contact_email', 'contact_phone',
    'template', 'theme',
    'country', 'province', 'city', 'postal_code', 'street', 'building_number', 'apartment_number',
    'default_vat_rate',
    'bank_account_number', 'bank_account_holder', 'bank_name', 'bank_transfer_enabled',
    'pickup_enabled', 'pay_on_pickup_enabled',
    'package', 'entitlements', 'subscription_ends_at', 'comped',
])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
            'theme' => 'array',
            'default_vat_rate' => VatRate::class,
            'bank_transfer_enabled' => 'boolean',
            'pickup_enabled' => 'boolean',
            'pay_on_pickup_enabled' => 'boolean',
            'entitlements' => 'array',
            'subscription_ends_at' => 'datetime',
            'comped' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Integracje sklepu (GA, w przyszłości PayU/InPost…). Jeden wiersz na typ.
     *
     * @return HasMany<ShopIntegration, $this>
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(ShopIntegration::class);
    }

    /**
     * Wiersz integracji danego typu (lub null). Czyta z załadowanej relacji,
     * żeby nie odpytywać bazy przy każdym wywołaniu w obrębie jednego requestu.
     */
    public function integration(IntegrationType $type): ?ShopIntegration
    {
        return $this->integrations->firstWhere('type', $type);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Alokuje kolejny numer zamówienia tego sklepu — atomowo, przez blokadę
     * wiersza sklepu (unika kolizji przy równoczesnych zamówieniach). Numeracja
     * jest ciągła i nieodzyskiwana: licznik rośnie niezależnie od anulowania czy
     * usunięcia logicznego zamówień. Wołane w transakcji składania zamówienia.
     */
    public function allocateOrderNumber(): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function (): int {
            $locked = self::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $next = $locked->last_order_number + 1;
            $locked->last_order_number = $next;
            $locked->save();

            $this->last_order_number = $next;

            return $next;
        });
    }

    /**
     * Tagi sklepu mające przynajmniej jeden aktywny produkt, z liczbą tych
     * produktów (`products_count`), najpopularniejsze najpierw. Wejście do
     * przeglądania po tagach (główna, chmura na wykazie bez filtra).
     *
     * @return \Illuminate\Support\Collection<int, Tag>
     */
    public function activeTagsByPopularity(): \Illuminate\Support\Collection
    {
        return $this->tags()
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->get();
    }

    /**
     * Usuwa osierocone tagi sklepu — takie, do których nie odnosi się już żaden
     * produkt (również usunięty miękko). Tag powstaje przy produkcie i ma żyć tak
     * długo, jak używa go choć jeden produkt; po usunięciu ostatniego znika, żeby
     * nie zaśmiecał podpowiedzi ani chmury tagów. Kasowanie tagu usuwa też jego
     * powiązania (kaskada FK product_tag). Trashed produkty liczą się jako
     * używające tagu — tag zamówionego, miękko usuniętego produktu zostaje.
     */
    public function pruneOrphanTags(): void
    {
        $this->tags()
            ->whereDoesntHave('products', fn ($query) => $query->withTrashed())
            ->each(fn (Tag $tag) => $tag->delete());
    }

    /**
     * Host storefrontu: dedykowana domena, jeśli ustawiona, w przeciwnym razie
     * subdomena {slug}.{central_domain}.
     */
    public function host(): string
    {
        return $this->domain ?: $this->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * Czy adres sklepu jest kompletny (wszystkie wymagane pola wypełnione).
     * Używane m.in. na pulpicie (postęp konfiguracji) i przy publikacji.
     */
    public function addressComplete(): bool
    {
        foreach (['street', 'building_number', 'postal_code', 'city', 'province'] as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Czy dane kontaktowe są kompletne (e-mail i telefon). Oba wymagane w panelu;
     * używane na pulpicie (postęp konfiguracji). E-mail bywa backfillowany z konta
     * właściciela, więc realnym „brakiem" jest zwykle telefon.
     */
    public function contactComplete(): bool
    {
        return filled($this->contact_email) && filled($this->contact_phone);
    }

    /**
     * Czy sklep ma przynajmniej jeden aktywny produkt. To jedyny wyznacznik
     * publicznej widoczności sklepu — pozostałe dane (adres, NIP, opis, logo) są
     * opcjonalne. Stan magazynowy pojedynczego produktu nie ma tu znaczenia
     * (wyczerpany produkt to sprawa produktu, nie całego sklepu).
     */
    public function hasActiveProducts(): bool
    {
        return $this->products()->where('is_active', true)->exists();
    }

    /**
     * Czy sklep jest publicznie widoczny (na żywo). Status w bazie jest
     * odzwierciedleniem tej widoczności — utrzymywanym automatycznie przez
     * App\Observers\ProductObserver — więc admin i storefront czytają gotową
     * kolumnę, bez liczenia produktów per sklep.
     */
    public function isVisible(): bool
    {
        return $this->status === ShopStatus::Active;
    }

    /**
     * Przelicza i zapisuje status sklepu z liczby aktywnych produktów:
     * ≥1 → Aktywny (widoczny), 0 → Szkic (ukryty). Wołane automatycznie przy
     * każdej zmianie produktu. Zapis tylko gdy status faktycznie się zmienia.
     */
    public function refreshVisibility(): void
    {
        $target = $this->hasActiveProducts() ? ShopStatus::Active : ShopStatus::Draft;

        if ($this->status !== $target) {
            $this->status = $target;
            $this->save();
        }
    }

    /**
     * Czy sklep udostępnia w kasie płatność przelewem na konto. Dwa warunki:
     * fiszka „Przelew na konto" włączona (Ustawienia) ORAZ numer konta wypełniony
     * (dane w „Mój sklep") — bez numeru nie ma dokąd przelać. Wykorzystane później
     * przy checkoucie (widoczność/wybór metody płatności).
     */
    public function bankTransferAvailable(): bool
    {
        return $this->bank_transfer_enabled && filled($this->bank_account_number);
    }

    /**
     * Czy sklep oferuje odbiór osobisty. Dwa warunki: fiszka włączona
     * (Ustawienia) ORAZ kompletny adres sklepu (adres odbioru bierze się z danych
     * sklepu — bez niego nie ma dokąd przyjść). Analogia do bankTransferAvailable.
     */
    public function pickupAvailable(): bool
    {
        return $this->pickup_enabled && $this->addressComplete();
    }

    /**
     * Czy sklep przyjmuje płatność przy odbiorze. Metoda płatności zależna od
     * dostawy: wymaga realnie dostępnego odbioru osobistego ORAZ włączonej
     * fiszki. Bez odbioru nie ma gdzie zapłacić „na miejscu".
     */
    public function payOnPickupAvailable(): bool
    {
        return $this->pay_on_pickup_enabled && $this->pickupAvailable();
    }

    /**
     * Skonfigurowany identyfikator Google Analytics / Tag Manager (G-… lub
     * GTM-…), niezależnie od włącznika. Do sprawdzenia „czy skonfigurowane"
     * (Integracje, Ustawienia). null, gdy integracji nie ma lub nie ma ID.
     */
    public function googleAnalyticsId(): ?string
    {
        return $this->integration(IntegrationType::GoogleAnalytics)?->config['tracking_id'] ?? null;
    }

    /**
     * Czy storefront ma faktycznie wstrzyknąć GA: dwa warunki — fiszka włączona
     * (Ustawienia) ORAZ identyfikator wpisany (Integracje). Analogia do
     * bankTransferAvailable(): stan efektywny, nie sam włącznik.
     */
    public function tracksWithGoogleAnalytics(): bool
    {
        $integration = $this->integration(IntegrationType::GoogleAnalytics);

        return $integration?->enabled === true && filled($integration->config['tracking_id'] ?? null);
    }

    /**
     * Telefon kontaktowy w czytelnej postaci („+48 668 196 229"). Przechowujemy
     * kanonicznie (48 + 9 cyfr); formatujemy dopiero do wyświetlenia — stopka,
     * maile, storefront. Null, gdy sklep nie ma jeszcze numeru.
     */
    public function formattedContactPhone(): ?string
    {
        return app(\App\Services\PhoneService::class)->format($this->contact_phone);
    }

    /**
     * Numer konta w czytelnej postaci — grupy po 4 cyfry (NN NNNN NNNN …).
     * Przechowujemy same cyfry; formatujemy dopiero do wyświetlenia.
     */
    public function formattedBankAccountNumber(): ?string
    {
        if (blank($this->bank_account_number)) {
            return null;
        }

        $number = $this->bank_account_number;

        // Polski NRB (26 cyfr) czyta się jako: 2 cyfry kontrolne + grupy po 4
        // (XX XXXX XXXX XXXX XXXX XXXX XXXX). Krótszy/nietypowy — grupujemy po 4 od startu.
        if (strlen($number) < 3) {
            return $number;
        }

        return substr($number, 0, 2).' '.trim(chunk_split(substr($number, 2), 4, ' '));
    }

    /**
     * Odbiorca przelewu do pokazania klientowi: jawnie ustawiony odbiorca, a gdy
     * pusty — nazwa firmy sklepu (domyślny odbiorca). Może być null, gdy sklep nie
     * uzupełnił jeszcze żadnej z tych danych.
     */
    public function bankAccountHolderName(): ?string
    {
        return filled($this->bank_account_holder)
            ? $this->bank_account_holder
            : ($this->company_name ?: null);
    }

    /**
     * Czy dany użytkownik może oglądać niepubliczną treść sklepu (sklep-szkic
     * przed publikacją, nieaktywny produkt): tylko właściciel i administrator.
     * Gość i obcy sprzedawca — nie. Wspólny predykat bramek storefrontu.
     */
    public function canBePreviewedBy(?User $user): bool
    {
        return $user !== null && ($user->id === $this->owner_id || $user->isAdmin());
    }

    /**
     * Slug aktywnego szablonu storefrontu, z siatką bezpieczeństwa: gdy sklep nie
     * ma wyboru albo trzyma slug, którego już nie ma w configu (szablon wycofany),
     * spadamy na domyślny szablon. Kod pyta o motyw przez te metody, nie o kolumnę.
     */
    public function templateSlug(): string
    {
        $slug = $this->template ?: config('themes.default_template');

        return config("themes.templates.{$slug}") !== null
            ? $slug
            : config('themes.default_template');
    }

    /**
     * Nazwa szablonu (PL, widoczna) rozwiązywana ze sluga — jak przy pakietach,
     * zmienialna w configu bez ruszania bazy.
     */
    public function templateName(): string
    {
        return config("themes.templates.{$this->templateSlug()}.name", $this->templateSlug());
    }

    /**
     * Kolor własny sklepu („kolor przewodni") w postaci kanonicznej „#RRGGBB",
     * lub null gdy nieustawiony/niepoprawny. Nadpisuje TYLKO token `brand`
     * (akcent); reszta kolorów dziedziczy z bazowej palety szablonu. Trzymany
     * w JSON `theme` pod kluczem `brand_color`.
     */
    public function brandColor(): ?string
    {
        $color = $this->theme['brand_color'] ?? null;

        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)
            ? strtoupper($color)
            : null;
    }

    /**
     * Klucz wybranej palety w ramach szablonu. Sprzedawca wybiera gotowca; brak
     * wyboru lub paleta nieobecna w szablonie (np. po zmianie szablonu) → domyślna
     * paleta szablonu. Wybór trzymany w JSON `theme` pod kluczem `palette`.
     *
     * „custom" to paleta WIRTUALNA — istnieje tylko wtedy, gdy realnie ustawiono
     * kolor własny. Gdy koloru brak (sprzedawca go wyczyścił), a wybór został na
     * „custom", spadamy na domyślną paletę szablonu (siatka bezpieczeństwa).
     */
    public function themePalette(): string
    {
        $slug = $this->templateSlug();
        $chosen = $this->theme['palette'] ?? null;

        if ($chosen === 'custom') {
            return $this->brandColor() !== null
                ? 'custom'
                : config("themes.templates.{$slug}.default_palette");
        }

        if ($chosen !== null && config("themes.templates.{$slug}.palettes.{$chosen}") !== null) {
            return $chosen;
        }

        return config("themes.templates.{$slug}.default_palette");
    }

    /**
     * Ile produktów na stronę wykazu — właściwość układu szablonu (config/themes.php),
     * z fallbackiem 12, gdy szablon jej nie ustawia. Szablon z większymi kadrami
     * pokazuje mniej na stronie.
     */
    public function productsPerPage(): int
    {
        return (int) (config("themes.templates.{$this->templateSlug()}.per_page") ?? 12);
    }

    /**
     * Rozwiązane tokeny kolorów (brand/brand_ink/surface/ink) dla aktywnego
     * szablonu + palety. To jedyne źródło, z którego storefront wyliczy zmienne
     * CSS (:root) — reszta odcieni powstaje z tych w CSS, nie jest wybierana.
     *
     * @return array<string, string>
     */
    public function themeTokens(): array
    {
        $slug = $this->templateSlug();
        $palette = $this->themePalette();

        // Kolor własny: baza = domyślna paleta szablonu (surface/ink dziedziczą,
        // więc sklep zostaje czytelny — jasny albo ciemny wg szablonu), a token
        // `brand` nadpisany kolorem sprzedawcy; `brand_ink` liczony dla kontrastu.
        if ($palette === 'custom') {
            $default = config("themes.templates.{$slug}.default_palette");
            $base = config("themes.templates.{$slug}.palettes.{$default}.tokens", []);
            $brand = $this->brandColor();

            return array_merge($base, [
                'brand' => $brand,
                'brand_ink' => Color::readableInkOn($brand),
            ]);
        }

        return config("themes.templates.{$slug}.palettes.{$palette}.tokens", []);
    }

    /**
     * Przypisuje pakiet i robi SNAPSHOT jego uprawnień: kopiuje `entitlements` z
     * configu do kolumny sklepu. Od tej chwili sklep żyje własnym zestawem —
     * późniejsza zmiana definicji pakietu w configu go nie dotyka („kupiłeś,
     * masz"). Wołane przy rejestracji, zakupie/zmianie pakietu i z konsoli admina.
     */
    public function assignPackage(string $slug): void
    {
        $entitlements = config("shop.packages.{$slug}.entitlements");

        if ($entitlements === null) {
            throw new \InvalidArgumentException("Nieznany pakiet: {$slug}");
        }

        $this->package = $slug;
        $this->entitlements = $entitlements;
        $this->save();
    }

    /**
     * Resolver uprawnień: aplikacja pyta „ile/czy X?", nie „jaki pakiet?".
     * Wygrywa zapisany snapshot sklepu; gdy brak klucza (sklep bez snapshotu —
     * legacy, albo nowe uprawnienie dodane po zakupie) — fallback do definicji
     * aktualnego pakietu w configu jako siatka bezpieczeństwa.
     */
    public function entitlement(string $key): mixed
    {
        $snapshot = $this->entitlements ?? [];

        if (array_key_exists($key, $snapshot)) {
            return $snapshot[$key];
        }

        return config("shop.packages.{$this->package}.entitlements.{$key}");
    }

    /**
     * Nazwa pakietu (PL, naklejka widoczna dla klienta) rozwiązywana ze sluga.
     * Slug w bazie jest stały; nazwę można zmieniać w configu bez ruszania danych.
     */
    public function packageName(): string
    {
        return config("shop.packages.{$this->package}.name", $this->package);
    }
}
