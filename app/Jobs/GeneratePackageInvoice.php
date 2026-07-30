<?php

namespace App\Jobs;

use App\Models\PackagePayment;
use App\Services\FakturowniaService;
use App\Services\PackagePaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Wystawia fakturę Kramio za pakiet w tle (kolejka `database`, drenowana z crona).
 *
 * `tries = 1` z tego samego powodu co przy fakturach zamówień: realne FV idą do
 * KSeF, a ślepe ponowienie mogłoby wystawić duplikat, gdyby request utworzył
 * dokument, ale odpowiedź nie wróciła. Nieudana próba zostaje w logu kanału
 * `fakturownia` — fakturę wystawiamy wtedy ręcznie.
 *
 * Idempotencja: guard `hasInvoice()` — drugi przebieg nic nie robi, bo
 * `invoice_id` jest jednym źródłem prawdy „już wystawiona".
 *
 * Wysyłka maila jest CELOWO poza jobem faktury: sprzedawca dostaje
 * podziękowanie od razu po wpłacie, a link do PDF osobną wiadomością, gdy
 * dokument faktycznie powstanie.
 */
class GeneratePackageInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly PackagePayment $payment) {}

    public function handle(FakturowniaService $fakturownia, PackagePaymentService $payments): void
    {
        $payment = $this->payment->fresh();

        if ($payment === null || $payment->hasInvoice()) {
            return;
        }

        $invoice = $fakturownia->createPackageInvoice($payment);

        if ($invoice === null) {
            return;
        }

        $payment->forceFill([
            'invoice_id' => $invoice['id'],
            'invoice_number' => $invoice['number'],
            'invoice_token' => $invoice['token'],
            'invoiced_at' => now(),
        ])->save();

        $payments->mailInvoiceReady($payment->fresh());
    }
}
