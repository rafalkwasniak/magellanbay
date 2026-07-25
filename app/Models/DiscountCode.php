<?php

namespace App\Models;

use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Enums\DiscountType;
use App\Support\Money;
use Database\Factories\DiscountCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kod rabatowy sklepu. Trzy typy (procent / kwota / darmowa wysyłka), dwa
 * zakresy (cały koszyk / jeden produkt) i cztery opcjonalne ograniczenia:
 * minimalna wartość produktów, okno czasowe, limit użyć i przypisanie do
 * konkretnego klienta.
 *
 * ZASADA NACZELNA: rabat procentowy i kwotowy schodzą wyłącznie z wartości
 * PRODUKTÓW (`items_total`) — koszt dostawy jest dla nich nietykalny. Jedyne
 * wyjście na wysyłkę to osobny typ „darmowa wysyłka", który z kolei nie tyka
 * produktów. Dzięki temu w zamówieniu nigdy nie ma dwuznaczności, od czego
 * policzono zniżkę.
 *
 * Kod nie zmienia ceny katalogowej produktu, więc NIE uruchamia obowiązku
 * Omnibus („najniższa cena z 30 dni") — to rabat na zamówienie, nie przecena.
 *
 * `shop_id` nie jest mass-assignable — kody tworzymy przez relację sklepu.
 */
#[Fillable([
    'code', 'type', 'value', 'scope', 'product_id',
    'min_items_total', 'starts_at', 'ends_at', 'max_uses', 'customer_id', 'is_active',
])]
class DiscountCode extends Model
{
    /** @use HasFactory<DiscountCodeFactory> */
    use HasFactory;

    /**
     * Znaki bez par mylących w odczycie (0/O, 1/I/L) — kod bywa przepisywany
     * z maila albo dyktowany przez telefon.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'scope' => DiscountScope::class,
            'value' => 'decimal:2',
            'min_items_total' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Kod zawsze wersalikami. Klient wpisze „lato10", sprzedawca zapisze
     * „Lato10" — a to ma być ten sam kod. Normalizacja siedzi w modelu, bo to
     * niezmiennik domeny (od niego zależy unikalność), nie kosmetyka formularza.
     *
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtoupper(trim($value)));
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Produkt, którego dotyczy kod (tylko przy zakresie `product`). `withTrashed`,
     * bo produkty kasujemy logicznie — kod ma dalej umieć pokazać, czego dotyczył.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Klient, dla którego wystawiono kod imienny (NULL = kod ogólnodostępny).
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Zamówienia, w których kod wykorzystano. To jest nasz „licznik użyć" —
     * osobnej tabeli wykorzystań świadomie nie ma.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Ile razy kod realnie wykorzystano. Anulowane zamówienia nie liczą się —
     * kod wraca do puli, bo za anulowane nikt nie zapłacił.
     *
     * Listy powinny podać `used_count` przez `withCount`, żeby nie robić zapytania
     * na wiersz; przy pojedynczym kodzie liczymy w locie i zapamiętujemy.
     */
    public function usedCount(): int
    {
        $counted = $this->getAttribute('used_count');

        if ($counted === null) {
            $counted = $this->orders()->countedAsSale()->count();
            $this->setAttribute('used_count', $counted);
        }

        return (int) $counted;
    }

    /**
     * Ile użyć zostało (NULL = bez limitu).
     */
    public function remainingUses(): ?int
    {
        if ($this->max_uses === null) {
            return null;
        }

        return max(0, $this->max_uses - $this->usedCount());
    }

    /**
     * Wyliczony stan kodu. Kolejność sprawdzeń oddaje to, co sprzedawca chce
     * usłyszeć najpierw: sam go wyłączyłeś → data minęła → pula się skończyła →
     * jeszcze nie wystartował → działa.
     */
    public function status(): DiscountStatus
    {
        if (! $this->is_active) {
            return DiscountStatus::Inactive;
        }

        $now = now();

        if ($this->ends_at !== null && $this->ends_at->isBefore($now)) {
            return DiscountStatus::Expired;
        }

        if ($this->max_uses !== null && $this->usedCount() >= $this->max_uses) {
            return DiscountStatus::Exhausted;
        }

        if ($this->starts_at !== null && $this->starts_at->isAfter($now)) {
            return DiscountStatus::Scheduled;
        }

        return DiscountStatus::Active;
    }

    /**
     * Czy koszyk osiąga próg minimalnej wartości. Próg patrzy na wartość
     * PRODUKTÓW — wysyłka nigdy nie pomaga go przekroczyć, inaczej droższy
     * kurier „odblokowywałby" rabat.
     */
    public function meetsMinimum(float $itemsTotal): bool
    {
        if ($this->min_items_total === null) {
            return true;
        }

        return $itemsTotal >= (float) $this->min_items_total;
    }

    /**
     * Kwota rabatu od podanej podstawy. Podstawą jest wartość produktów w
     * koszyku (zakres `cart`) albo wartość jednej linii (zakres `product`) —
     * wybór należy do warstwy wyżej, tu liczymy samą matematykę.
     *
     * Rabat kwotowy obcinamy do podstawy: zamówienie nigdy nie może zejść
     * poniżej zera ani „pożreć" kosztu wysyłki.
     */
    public function discountOn(float $base): float
    {
        $base = max(0.0, $base);

        return match ($this->type) {
            DiscountType::Percent => round($base * (float) $this->value / 100, 2),
            DiscountType::Amount => round(min((float) $this->value, $base), 2),
            DiscountType::FreeShipping => 0.0,
        };
    }

    /**
     * Sam rabat w jednym słowie: „10%", „20 zł", „Darmowa wysyłka". Używa tego
     * lista kodów i podsumowanie w formularzu — jedno źródło zapisu.
     */
    public function discountLabel(): string
    {
        return match ($this->type) {
            DiscountType::Percent => rtrim(rtrim(number_format((float) $this->value, 2, ',', ' '), '0'), ',').'%',
            DiscountType::Amount => Money::pln((float) $this->value),
            DiscountType::FreeShipping => 'Darmowa wysyłka',
        };
    }

    /**
     * Czego dotyczy rabat — „Cały koszyk" albo nazwa produktu. Skasowany produkt
     * nadal się pokaże (relacja `withTrashed`), bo historia kodu ma być czytelna.
     */
    public function targetLabel(): string
    {
        if ($this->scope === DiscountScope::Cart) {
            return DiscountScope::Cart->label();
        }

        return $this->product?->name ?? 'Produkt usunięty';
    }

    /**
     * Okno ważności w jednym zdaniu: „Bezterminowo", „do 31.08.2026",
     * „od 1.08.2026", „1.08–31.08.2026".
     */
    public function validityLabel(): string
    {
        if ($this->starts_at === null && $this->ends_at === null) {
            return 'Bezterminowo';
        }

        if ($this->starts_at === null) {
            return 'do '.$this->ends_at->format('j.m.Y');
        }

        if ($this->ends_at === null) {
            return 'od '.$this->starts_at->format('j.m.Y');
        }

        return $this->starts_at->format('j.m.Y').'–'.$this->ends_at->format('j.m.Y');
    }

    /**
     * Wykorzystanie: „3 / 10" przy limicie, samo „3" przy kodzie bez limitu.
     */
    public function usageLabel(): string
    {
        $used = $this->usedCount();

        return $this->max_uses === null ? (string) $used : $used.' / '.$this->max_uses;
    }

    /**
     * Czy kod jest imienny (wystawiony konkretnemu klientowi).
     */
    public function isPersonal(): bool
    {
        return $this->customer_id !== null;
    }

    /**
     * Czy ten klient może użyć kodu. Kod ogólnodostępny — każdy; imienny —
     * wyłącznie jego właściciel (gość, czyli NULL, nigdy).
     */
    public function isUsableBy(?Customer $customer): bool
    {
        if (! $this->isPersonal()) {
            return true;
        }

        return $customer !== null && $customer->id === $this->customer_id;
    }

    /**
     * Tylko kody widoczne dla klienta w koszyku — reszta warunków (próg, klient,
     * zakres) zależy od konkretnego koszyka i liczy się wyżej.
     *
     * @param  Builder<DiscountCode>  $query
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $now = now();

        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Losowy, czytelny kod do przycisku „Wygeneruj" w panelu. Unikalność
     * sprawdza walidacja przy zapisie (kolizja przy 31^8 jest teoretyczna).
     */
    public static function randomCode(int $length = 8): string
    {
        $alphabet = self::CODE_ALPHABET;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, mb_strlen($alphabet) - 1)];
        }

        return $code;
    }

    /**
     * Podpowiedź kodu zbudowana z nazwy (np. „Kwiaty wiosenne" → KWIATYWIOSENNE).
     * Gdy nazwa nic sensownego nie daje — losowy.
     */
    public static function suggestFrom(?string $name): string
    {
        $slug = mb_strtoupper(Str::of($name ?? '')->ascii()->replaceMatches('/[^A-Za-z0-9]/', '')->limit(16, '')->value());

        return $slug !== '' ? $slug : self::randomCode();
    }
}
