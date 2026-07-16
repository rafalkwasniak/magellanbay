<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\Order;
use App\Services\FakturowniaService;
use App\Services\OrderMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Wystawia fakturę VAT dla zamówienia w tle (kolejka `database`, drenowana
 * krótkim `queue:work --stop-when-empty` z crona — bez demona, LVE-safe).
 *
 * `tries = 1`: ŻADNYCH automatycznych ponowień. Fakturownia nie ma sandboxa, a
 * realne FV idą do KSeF — ślepe ponowienie mogłoby wystawić duplikat (np. gdy
 * request utworzył FV, ale odpowiedź nie wróciła). Porażka zostawia
 * `invoice_status = failed`; sprzedawca ponawia świadomie przyciskiem.
 *
 * Idempotencja: guard `hasInvoice()` na wejściu — gdyby job wpadł dwa razy, drugi
 * przebieg nic nie robi (kolumna `invoice_id` = jedno źródło prawdy „już jest").
 */
class GenerateInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly Order $order) {}

    public function handle(FakturowniaService $fakturownia, OrderMailer $mailer): void
    {
        $order = $this->order->fresh();

        // Zamówienie zniknęło albo FV już jest — nic nie robimy (idempotencja).
        if ($order === null || $order->hasInvoice()) {
            return;
        }

        $trace = $fakturownia->createInvoice($order);

        if ($trace === null || blank($trace['id'])) {
            $order->forceFill(['invoice_status' => InvoiceStatus::Failed])->save();

            return;
        }

        // Zapis śladu FV NAJPIERW (zanim cokolwiek innego) — od tej chwili
        // `hasInvoice()` = true, więc nawet ponowny przebieg nie wystawi drugiej.
        $order->forceFill([
            'invoice_id' => $trace['id'],
            'invoice_number' => $trace['number'],
            'invoice_token' => $trace['token'],
            'invoiced_at' => now(),
            'invoice_status' => null,
        ])->save();

        // Mail „Pobierz FV" wysyła NASZ system (spójność brandu), nie Fakturownia.
        // Kolejkujemy do outboxu — cron `email:dispatch` dostarczy jak każdy inny.
        $mailer->invoiceReady($order);
    }
}
