<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\SaleUnit;
use App\Enums\ShopStatus;
use App\Enums\VatRate;
use App\Observers\ShopObserver;
use App\Support\Color;
use App\Support\Excerpt;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sklep jednego sprzedawcy. `slug` = etykieta subdomeny ({slug}.{central_domain}).
 * `domain` = opcjonalna dedykowana domena (np. mojsklep.pl). `owner_id` nie jest
 * mass-assignable — sklep tworzymy przez relację usera.
 */
#[ObservedBy(ShopObserver::class)]
#[Fillable([
    'name', 'slug', 'domain', 'status', 'description', 'company_name', 'nip', 'logo_path',
    'contact_email', 'contact_phone',
    'template', 'theme',
    'country', 'province', 'city', 'postal_code', 'street', 'building_number', 'apartment_number',
    'default_vat_rate', 'default_sale_unit',
    'bank_account_number', 'bank_account_holder', 'bank_name', 'bank_transfer_enabled',
    'pickup_enabled', 'pay_on_pickup_enabled',
    'courier_enabled', 'courier_cost', 'courier_free_from',
    'parcel_locker_enabled', 'parcel_locker_cost', 'parcel_locker_free_from',
    'package', 'entitlements', 'price_yearly', 'subscription_ends_at', 'comped',
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
            'default_sale_unit' => SaleUnit::class,
            'bank_transfer_enabled' => 'boolean',
            'pickup_enabled' => 'boolean',
            'pay_on_pickup_enabled' => 'boolean',
            'courier_enabled' => 'boolean',
            'courier_cost' => 'decimal:2',
            'courier_free_from' => 'decimal:2',
            'parcel_locker_enabled' => 'boolean',
            'parcel_locker_cost' => 'decimal:2',
            'parcel_locker_free_from' => 'decimal:2',
            'entitlements' => 'array',
            'price_yearly' => 'decimal:2',
            'subscription_ends_at' => 'datetime',
            'comped' => 'boolean',
            'unseen_orders_count' => 'integer',
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
     * Strony tekstowe sklepu („Informacje") — Regulamin i strony sprzedawcy.
     * Jedna wspólna kolejność (position) dla menu i stopki.
     *
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
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
     * Klienci tego sklepu (konta storefrontu). Odseparowani między sklepami —
     * ten sam e-mail bywa klientem wielu sklepów niezależnie.
     *
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
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
     * Opis sklepu jako czysty tekst (bez HTML, ze zwiniętymi białymi znakami) —
     * do liczenia długości progu „O sklepie". `<br>` i tagi nie zawyżają wyniku.
     */
    public function aboutPlainText(): string
    {
        return Excerpt::plainText($this->description);
    }

    /**
     * Zajawka „O sklepie" do kafelka na stronie głównej. Dokładnie ta sama reguła
     * co dla promowanych stron (`Page::excerpt()`) — „O sklepie" jest tam zwykłym
     * kafelkiem, różni się tylko tym, skąd bierze treść i że idzie pierwsze.
     */
    public function aboutExcerpt(): Excerpt
    {
        return Excerpt::fromHtml($this->description, (int) config('pages.excerpt_length'));
    }

    /**
     * Czy istnieje wirtualna strona „O sklepie" — tzn. opis sklepu jest niepusty.
     * Strona renderuje zawsze, gdy jest jakakolwiek treść (długość rządzi tylko
     * obecnością w menu, nie istnieniem strony).
     */
    public function hasAbout(): bool
    {
        return $this->aboutPlainText() !== '';
    }

    /**
     * Czy „O sklepie" zasługuje na własną pozycję w menu „Informacje": opis
     * dłuższy niż próg czystego tekstu z configu. Poniżej progu adres nadal
     * działa, brakuje tylko pozycji w menu.
     *
     * Dotyczy WYŁĄCZNIE menu. Kafelkiem na stronie głównej rządzi `hasAbout()`
     * (czy jest treść) i `aboutExcerpt()` (co pokazać) — próg nie ma tam nic
     * do rzeczy, mimo że `pages.excerpt_length` ma dziś tę samą wartość.
     */
    public function aboutInMenu(): bool
    {
        return mb_strlen($this->aboutPlainText()) >= (int) config('pages.about.menu_threshold');
    }

    /**
     * Kanoniczny adres wirtualnej strony „O sklepie" na storefroncie (względny —
     * storefront to jeden host). Slug z configu, w rodzinie /informacje/….
     */
    public function aboutPath(): string
    {
        return '/informacje/'.config('pages.about.slug');
    }

    /**
     * Kanoniczny adres Polityki prywatności na storefroncie (w rodzinie
     * /informacje/…). Treść jest NASZA (Kramio), ale adres i prezentacja spójne
     * z resztą działu „Informacje".
     */
    public function privacyPath(): string
    {
        return '/informacje/'.config('pages.privacy.slug');
    }

    /**
     * Pozycje menu „Informacje" — jedno źródło dla nagłówka, skorupy stron i
     * stopki. Kolejność: wirtualna „O sklepie" jako PIERWSZA (tylko gdy opis
     * przekracza próg), potem opublikowane strony wg `position` (Regulamin też —
     * to strona), a Polityka prywatności ZAWSZE jako OSTATNIA. Jedna lista, jedna
     * kolejność.
     *
     * @return list<array{label: string, url: string}>
     */
    public function informationMenu(): array
    {
        $items = [];

        if ($this->aboutInMenu()) {
            $items[] = ['label' => config('pages.about.title'), 'url' => $this->aboutPath()];
        }

        foreach ($this->pages()->published()->get() as $page) {
            $items[] = ['label' => $page->title, 'url' => $page->storefrontPath()];
        }

        $items[] = ['label' => config('pages.privacy.title'), 'url' => $this->privacyPath()];

        return $items;
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
     * Czy sklep oferuje dostawę kurierem (Poziom 1 — bez integracji). W
     * przeciwieństwie do odbioru NIE wymaga adresu sklepu: paczkę wysyła
     * sprzedawca do klienta, więc do pokazania metody w kasie wystarczy włączona
     * fiszka. Koszt bywa 0 (kurier gratis) — dlatego warunkiem jest sam włącznik,
     * a nie „koszt > 0". Analogia do bankTransferAvailable(): stan efektywny.
     */
    public function courierAvailable(): bool
    {
        return $this->courier_enabled;
    }

    /**
     * Efektywny koszt dostawy kurierem dla danej wartości koszyka (brutto). Zwraca
     * 0, gdy sklep ustawił próg darmowej dostawy i wartość produktów go osiąga —
     * w innym wypadku ustalony koszt kuriera. Próg NULL = brak darmowej dostawy.
     */
    public function courierCostFor(float $itemsGross): float
    {
        $freeFrom = $this->courier_free_from;

        if ($freeFrom !== null && $itemsGross >= (float) $freeFrom) {
            return 0.0;
        }

        return (float) ($this->courier_cost ?? 0);
    }

    /**
     * Czy sklep oferuje dostawę do paczkomatu InPost (Poziom 1 — bez integracji).
     * Jak kurier: nie wymaga adresu sklepu ani konta sprzedawcy w InPoście, więc
     * warunkiem jest sam włącznik (koszt bywa 0 = gratis). Mapa NIE jest
     * warunkiem — gdy jej nie ma lub nie wstanie, klient wpisuje kod z palca.
     */
    public function parcelLockerAvailable(): bool
    {
        return $this->parcel_locker_enabled;
    }

    /**
     * Efektywny koszt dostawy do paczkomatu dla danej wartości koszyka (brutto).
     * Bliźniak courierCostFor(): próg osiągnięty → 0, inaczej ustalony koszt.
     * Próg NULL = brak darmowej dostawy. Progi kuriera i paczkomatu są NIEZALEŻNE
     * — sprzedawca może dać gratis w paczkomacie, a kuriera liczyć zawsze.
     */
    public function parcelLockerCostFor(float $itemsGross): float
    {
        $freeFrom = $this->parcel_locker_free_from;

        if ($freeFrom !== null && $itemsGross >= (float) $freeFrom) {
            return 0.0;
        }

        return (float) ($this->parcel_locker_cost ?? 0);
    }

    /**
     * Efektywny koszt dostawy dla danej metody i wartości koszyka (brutto).
     * JEDNO źródło dla kasy i OrderService — wcześniej koszt liczyło się jako
     * „wysyłka → courierCostFor()", co przy paczkomacie kazałoby mu płacić
     * cenę kuriera. Nowa metoda dostawy wywali tu UnhandledMatchError i dobrze:
     * cennik trzeba dopisać świadomie, a nie odziedziczyć po cichu (ta sama
     * zasada, co przy ścieżce statusów w OrderFlow).
     */
    public function deliveryCostFor(DeliveryMethod $method, float $itemsGross): float
    {
        return match ($method) {
            DeliveryMethod::Pickup => 0.0,
            DeliveryMethod::Courier => $this->courierCostFor($itemsGross),
            DeliveryMethod::ParcelLocker => $this->parcelLockerCostFor($itemsGross),
        };
    }

    /**
     * Próg darmowej dostawy dla danej metody (null = brak progu). Lustro
     * deliveryCostFor() — kasa pokazuje z tego podpowiedź „Darmowa dostawa od…".
     */
    public function deliveryFreeFrom(DeliveryMethod $method): ?float
    {
        $value = match ($method) {
            DeliveryMethod::Pickup => null,
            DeliveryMethod::Courier => $this->courier_free_from,
            DeliveryMethod::ParcelLocker => $this->parcel_locker_free_from,
        };

        return $value !== null ? (float) $value : null;
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
     * Adres konta Fakturowni (np. „https://twojadomena.fakturownia.pl") — baza
     * zarówno dla wywołań API, jak i publicznego linku do PDF faktury. null, gdy
     * integracji nie ma lub nie ma adresu.
     */
    public function fakturowniaAccountUrl(): ?string
    {
        return $this->integration(IntegrationType::Invoicing)?->config['account_url'] ?? null;
    }

    /**
     * Token API Fakturowni (sekret, przechowywany zaszyfrowany w config). Używany
     * przez usługę wystawiającą FV; w UI nigdy nie odbijamy go z powrotem. null,
     * gdy brak integracji lub tokenu.
     */
    public function fakturowniaToken(): ?string
    {
        return $this->integration(IntegrationType::Invoicing)?->config['api_token'] ?? null;
    }

    /**
     * Czy Fakturownia jest w pełni skonfigurowana (adres + token). Nie sprawdza
     * uprawnienia pakietu ani statusu zamówienia — to sama gotowość integracji.
     * Pełna bramka „czy można wystawić FV" łączy to z entitlement('invoices').
     */
    public function invoicingConfigured(): bool
    {
        return filled($this->fakturowniaAccountUrl()) && filled($this->fakturowniaToken());
    }

    /**
     * Stan efektywny Fakturowni: włącznik z Ustawień ORAZ komplet konfiguracji
     * (adres + token). To warunek, pod którym w karcie zamówienia pokazujemy
     * przycisk „Stwórz fakturę VAT" — w połączeniu z entitlement('invoices').
     * Analogia do tracksWithGoogleAnalytics(): sam włącznik bez konfiguracji nic
     * nie znaczy.
     */
    public function invoicingEnabled(): bool
    {
        return $this->integration(IntegrationType::Invoicing)?->enabled === true && $this->invoicingConfigured();
    }

    /**
     * Klucz dostępu do API Paynow (nagłówek Api-Key). Sam w sobie bezużyteczny
     * bez klucza podpisu — nie da się nim podpisać żądania — dlatego, inaczej niż
     * klucz podpisu, wolno go pokazać w formularzu sprzedawcy. null, gdy brak.
     */
    public function paynowApiKey(): ?string
    {
        return $this->integration(IntegrationType::Payments)?->config['api_key'] ?? null;
    }

    /**
     * Klucz obliczania podpisu Paynow (sekret: podpisuje żądania i weryfikuje
     * webhooki). Przechowywany zaszyfrowany; w UI nigdy nie odbijany. null, gdy brak.
     */
    public function paynowSignatureKey(): ?string
    {
        return $this->integration(IntegrationType::Payments)?->config['signature_key'] ?? null;
    }

    /**
     * Środowisko Paynow: 'sandbox' (domyślnie) albo 'production'. Rozstrzyga, który
     * adres API z config('services.paynow.base_url') wybrać przy wywołaniach.
     */
    public function paynowEnvironment(): string
    {
        return $this->integration(IntegrationType::Payments)?->config['environment'] ?? 'sandbox';
    }

    /**
     * Czy Paynow jest w pełni skonfigurowany (oba klucze). Nie sprawdza włącznika
     * ani uprawnienia pakietu — sama gotowość integracji. Analogia do
     * invoicingConfigured().
     */
    public function onlinePaymentsConfigured(): bool
    {
        return filled($this->paynowApiKey()) && filled($this->paynowSignatureKey());
    }

    /**
     * Stan efektywny płatności online: włącznik (Ustawienia) ORAZ komplet kluczy.
     * To warunek, pod którym metoda „Płatność online" pojawia się w kasie — brama
     * pakietu (entitlement('online_payments')) dojdzie osobno, na końcu wdrożenia.
     */
    public function onlinePaymentsEnabled(): bool
    {
        return $this->integration(IntegrationType::Payments)?->enabled === true && $this->onlinePaymentsConfigured();
    }

    /**
     * Czy po opłaceniu online sklep chce automatycznie wystawić FV (checkbox przy
     * integracji Paynow). Sama flaga — realne wystawienie i tak przechodzi przez
     * `Order::canBeInvoiced()` (Fakturownia włączona, uprawnienie, brak dubla), więc
     * zaznaczona bez włączonej Fakturowni po prostu nic nie robi.
     */
    public function autoInvoiceAfterPayment(): bool
    {
        return ($this->integration(IntegrationType::Payments)?->config['auto_invoice'] ?? false) === true;
    }

    /**
     * Adres powiadomień (webhooka) Paynow dla tego sklepu — do przeklejenia w
     * panelu operatora. Na własnym hoście sklepu (subdomena/domena), bo tam
     * kieruje kupującego i tam sprzedawca konfiguruje Paynow. Powiadomień NIE da
     * się ustawić z naszego systemu — sprzedawca wkleja ten adres ręcznie.
     */
    public function paynowWebhookUrl(): string
    {
        return 'https://'.$this->host().'/platnosci/paynow/webhook';
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
     * Adres siedziby w jednej linii: „Okrzei 73/5, 42-582 Rogoźnik". Null, gdy nie
     * ma z czego złożyć — dane firmowe są OPCJONALNE (wymagany jest tylko kontakt,
     * patrz ShopProfileRequest), więc sklep bez adresu to normalny stan, nie błąd.
     *
     * Kod pocztowy bierzemy jak stoi: obie ścieżki zapisu (formularz profilu oraz
     * podpowiedź z GUS) normalizują go do postaci NN-NNN.
     */
    public function addressLine(): ?string
    {
        $house = trim((string) $this->building_number);

        if (filled($this->apartment_number)) {
            $house .= '/'.trim((string) $this->apartment_number);
        }

        $parts = array_filter([
            trim(trim((string) $this->street).' '.$house),
            trim(trim((string) $this->postal_code).' '.trim((string) $this->city)),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
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
     * Gęstość wykazu dobrana do SKALI katalogu: {columns, per_page}. Bierzemy
     * najmniejszy układ z drabinki (config themes.listing.steps), przy którym
     * wszystkie aktywne produkty mieszczą się w `max_pages` podstronach — rosnąc
     * najpierw wierszami przy 3 kolumnach, a dopiero potem skokiem na 4 kolumny.
     * Wielkość kafla robią kolumny (3 = duże/wyraziste, 4 = gęstsze); `rows`
     * steruje tylko długością strony (per_page = columns × rows).
     *
     * Liczba liczona z aktywnych produktów CAŁEGO sklepu, nie z przefiltrowanego
     * widoku — dzięki temu skala (i układ) są stałe niezależnie od tagów/sortu:
     * treść nie „pływa" przy filtrowaniu.
     *
     * @return array{columns: int, per_page: int}
     */
    public function listingDensity(): array
    {
        $count = $this->products()->where('is_active', true)->count();
        $steps = config('themes.listing.steps');
        $maxPages = (int) config('themes.listing.max_pages', 3);

        foreach ($steps as $step) {
            $perPage = $step['columns'] * $step['rows'];
            if ($count <= $maxPages * $perPage) {
                return ['columns' => $step['columns'], 'per_page' => $perPage];
            }
        }

        // Powyżej ostatniego stopnia zostaje sufit — podstron po prostu przybywa.
        $last = end($steps);

        return ['columns' => $last['columns'], 'per_page' => $last['columns'] * $last['rows']];
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
        $package = config("shop.packages.{$slug}");

        if ($package === null || ! isset($package['entitlements'])) {
            throw new \InvalidArgumentException("Nieznany pakiet: {$slug}");
        }

        $this->package = $slug;
        $this->entitlements = $package['entitlements'];
        $this->price_yearly = $package['price_yearly'] ?? 0;
        $this->save();
    }

    /**
     * Cena roczna (BRUTTO, zł) sklepu: wygrywa zapisany snapshot per sklep,
     * a gdy go brak (legacy) — fallback do aktualnego cennika pakietu w configu.
     * Analogicznie do entitlement(): źródłem prawdy jest to, co zapisane na sklepie
     * (deal per klient, cena zamrożona), nie definicja pakietu.
     */
    public function priceYearly(): float
    {
        return (float) ($this->price_yearly
            ?? config("shop.packages.{$this->package}.price_yearly", 0));
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
