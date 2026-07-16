<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Support\OrderFlow;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Zamówienie sklepu. Wszystkie dane (kupujący, adres, ceny) to migawka z chwili
 * złożenia — nie odczytujemy ich z bieżących produktów/profilu. `number` to numer
 * per-sklep prezentowany klientom; `shop_id` nie jest mass-assignable (tworzymy
 * przez relację sklepu). Usuwanie wyłącznie logiczne (SoftDeletes).
 */
#[Fillable([
    'number', 'customer_id', 'status',
    'buyer_name', 'buyer_surname', 'buyer_email', 'buyer_phone',
    'is_company', 'company_name', 'company_nip',
    'company_street', 'company_building_number', 'company_apartment_number', 'company_postal_code', 'company_city',
    'ship_street', 'ship_building_number', 'ship_apartment_number', 'ship_postal_code', 'ship_city',
    'delivery_method', 'delivery_cost', 'payment_method',
    'items_total', 'total_net', 'total_vat', 'total_gross', 'note',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'is_company' => 'boolean',
            'delivery_cost' => 'decimal:2',
            'items_total' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'invoiced_at' => 'datetime',
            'invoice_status' => \App\Enums\InvoiceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Konto klienta, do którego przypięto zamówienie (lub null dla gościa).
     * Przypięcie następuje po e-mailu w obrębie sklepu — przy aktywacji konta
     * lub w kasie, gdy e-mail ma już konto.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Oś czasu zmian statusu (od najstarszej). Pierwsza linia osi na widoku to
     * `created_at` samego zamówienia; tu są kolejne przejścia.
     *
     * @return HasMany<OrderStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->oldest('id');
    }

    /**
     * Zamówienia liczone jako ZAKUP — do wszystkich ilości i kwot (przychód,
     * liczba zamówień, liczba sztuk). Anulowane odpadają: zostają w systemie
     * wyłącznie informacyjnie, jako ślad, że tak było — bo zamówienie mogło być
     * opłacone i dopiero potem anulowane, więc nie wolno go wymazać. Ale zakupem
     * nie jest, więc nie może podbijać żadnej statystyki.
     *
     * Nie mylić z „czy pokazać na liście" — listy pokazują też anulowane. Ten
     * scope dotyczy wyłącznie liczenia.
     */
    #[Scope]
    protected function countedAsSale(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Cancelled->value);
    }

    /**
     * Ścieżka statusów TEGO zamówienia — wynika z migawki metody płatności i
     * dostawy, więc jest stała przez całe życie zamówienia (zmiana ustawień
     * sklepu nie przestawia ścieżki już złożonym zamówieniom).
     */
    public function flow(): OrderFlow
    {
        return OrderFlow::forOrder($this);
    }

    /**
     * PRYMITYW: zmienia status i dopisuje zdarzenie do osi czasu. Zwraca to
     * zdarzenie albo null, gdy status się nie zmienia — pustym przejściem nie
     * zaśmiecamy historii. Jedyne miejsce, które modyfikuje `status`, więc oś
     * czasu zawsze jest kompletna.
     *
     * Nie sprawdza ścieżki, nie rusza magazynu i NIE WYSYŁA MAILI — z panelu
     * wołaj `OrderStatusChanger`, który dokłada te trzy rzeczy. Bezpośrednio
     * tylko tam, gdzie świadomie chcesz sam zapis (np. migracje danych).
     */
    public function changeStatus(OrderStatus $to, ?string $note = null): ?OrderStatusEvent
    {
        if ($to === $this->status) {
            return null;
        }

        $from = $this->status;
        $this->status = $to;
        $this->save();

        return $this->statusEvents()->create([
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }

    /**
     * Czy zamówienie ma już wystawioną fakturę VAT. `invoice_id` (identyfikator w
     * Fakturowni) jest zarazem gardem idempotencji — FV wystawiamy tylko raz.
     */
    public function hasInvoice(): bool
    {
        return filled($this->invoice_id);
    }

    /**
     * Publiczny link do PDF faktury w Fakturowni: `{konto}/invoice/{token}.pdf`.
     * Token nie wymaga naszej autoryzacji ani api_token — ściąga wprost. null,
     * gdy FV jeszcze nie ma tokenu albo sklep stracił konfigurację adresu.
     */
    public function invoicePdfUrl(): ?string
    {
        $accountUrl = $this->shop?->fakturowniaAccountUrl();

        if (blank($this->invoice_token) || blank($accountUrl)) {
            return null;
        }

        return rtrim($accountUrl, '/').'/invoice/'.$this->invoice_token.'.pdf';
    }

    /**
     * Czy w tej chwili trwa generowanie FV (job w kolejce/robocie). UI pokazuje
     * wtedy „FV w przygotowaniu" i blokuje przycisk, by nie zlecić drugi raz.
     */
    public function isInvoicePending(): bool
    {
        return $this->invoice_status === \App\Enums\InvoiceStatus::Pending;
    }

    /**
     * Czy ostatnia próba wystawienia FV się nie powiodła. FV nie ma (`invoice_id`
     * pusty), więc sprzedawca może ponowić — `canBeInvoiced()` znów przepuszcza.
     */
    public function invoiceFailed(): bool
    {
        return $this->invoice_status === \App\Enums\InvoiceStatus::Failed;
    }

    /**
     * Czy z tego zamówienia można teraz wystawić fakturę VAT. Pełna bramka:
     * pakiet daje uprawnienie, Fakturownia jest włączona i skonfigurowana, FV
     * jeszcze nie było (idempotencja) i nie trwa już generowanie. Świadomie BEZ
     * progu statusu — sprzedawca decyduje sam (ustalone: „od dowolnego statusu").
     */
    public function canBeInvoiced(): bool
    {
        return ! $this->hasInvoice()
            && ! $this->isInvoicePending()
            && $this->shop?->entitlement('invoices') === true
            && $this->shop->invoicingEnabled();
    }
}
