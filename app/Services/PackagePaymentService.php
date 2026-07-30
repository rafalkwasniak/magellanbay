<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Jobs\GeneratePackageInvoice;
use App\Models\EmailMessage;
use App\Models\PackagePayment;
use App\Models\Shop;
use App\Support\Money;
use App\Support\PackageFeatures;
use App\Support\PackageUpgrade;
use App\Support\Vocative;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Zakup pakietu Kramio: od kliknięcia „Kup" do ustawienia pakietu po wpłacie.
 *
 * `start()` — wycenia przez PackageUpgrade (TA SAMA wycena, którą sprzedawca
 * widział na ekranie), zapisuje MIGAWKĘ w `package_payments` i tworzy płatność
 * na koncie platformy. `apply()` — po potwierdzeniu wpłaty ustawia pakiet,
 * cenę i datę Z MIGAWKI; idempotentne, bo webhooki się dublują.
 *
 * UPRAWNIENIA SĄ LEPKIE: nowy snapshot to preset kupionego pakietu SCALONY ze
 * starym snapshotem — bool przez OR, liczby przez max. Ręczne nadanie (moduł
 * dany komuś gestem poza pakietem) nie może zniknąć przez to, że klient
 * dokupił wyższy pakiet.
 */
class PackagePaymentService
{
    public function __construct(private PaynowService $paynow) {}

    /**
     * Rozpoczyna zakup: migawka wyceny + płatność w Paynow. Zwraca adres
     * przekierowania do bramki albo null (brak konfiguracji / błąd API / pakiet
     * nie do kupienia z tego stanu).
     */
    public function start(Shop $shop, string $targetPackage, string $continueUrl): ?string
    {
        $quote = PackageUpgrade::quote($shop, $targetPackage);

        // Kupić można tylko ruch W GÓRĘ z realną kwotą. Obniżka idzie przez
        // kontakt (wchodzi przy odnowieniu), zero złotych nie istnieje w cenniku.
        if (! in_array($quote['kind'], ['full', 'credit'], true) || $quote['amount'] <= 0) {
            return null;
        }

        $payment = $shop->packagePayments()->create([
            'target_package' => $targetPackage,
            'amount' => $quote['amount'],
            'credit' => $quote['credit'],
            'new_ends_at' => $quote['new_ends_at'],
            'status' => PackagePayment::STATUS_PENDING,
        ]);

        $created = $this->paynow->createPlatformPayment(
            $quote['amount'],
            'Kramio — pakiet '.config("shop.packages.{$targetPackage}.name").' (rok)',
            $shop->owner?->email ?? '',
            'pakiet-'.$payment->id.'-'.Str::lower(Str::random(6)),
            $continueUrl,
        );

        if ($created === null) {
            $payment->forceFill(['status' => 'failed'])->save();

            return null;
        }

        $payment->forceFill(['payment_id' => $created['paymentId']])->save();

        $this->mailPaymentStarted($shop, $payment, $created['redirectUrl']);

        return $created['redirectUrl'];
    }

    /**
     * Stosuje opłacony zakup: pakiet, snapshot (lepki), cena i termin z migawki.
     * Wołane z webhooka po CONFIRMED. Drugie wywołanie nic nie robi.
     */
    public function apply(PackagePayment $payment): void
    {
        if ($payment->isApplied()) {
            return;
        }

        $shop = $payment->shop;
        $preset = config("shop.packages.{$payment->target_package}.entitlements", []);
        $current = $shop->entitlements ?? [];

        // Scalenie lepkie: bool OR, liczby max — ręczne nadania przeżywają zakup.
        $merged = $preset;
        foreach ($current as $key => $value) {
            $merged[$key] = is_bool($value)
                ? ($value || (bool) ($preset[$key] ?? false))
                : max((int) $value, (int) ($preset[$key] ?? 0));
        }

        $shop->forceFill([
            'package' => $payment->target_package,
            'entitlements' => $merged,
            'price_yearly' => config("shop.packages.{$payment->target_package}.price_yearly"),
            'subscription_ends_at' => $payment->new_ends_at,
        ])->save();

        $payment->forceFill([
            'status' => PackagePayment::STATUS_PAID,
            'paid_at' => $payment->paid_at ?? now(),
            'applied_at' => now(),
        ])->save();

        $shop->refresh()->recordPackageChange(\App\Models\PackageChange::SOURCE_PAYMENT, $payment);

        Log::channel('paynow')->info('Pakiet ustawiony po wpłacie.', [
            'shop_id' => $shop->id,
            'package' => $payment->target_package,
            'payment_id' => $payment->payment_id,
        ]);

        $this->mailPackageActivated($shop->fresh(), $payment);

        // Faktura w tle: sprzedawca nie czeka na Fakturownię, a webhook Paynow
        // dostaje szybką odpowiedź (operator ponawia powiadomienia po timeoucie).
        GeneratePackageInvoice::dispatch($payment->fresh());
    }

    /**
     * Mail z gotową fakturą — osobno od podziękowania, bo dokument powstaje
     * chwilę później (job w kolejce). Przycisk prowadzi wprost do PDF-a.
     */
    public function mailInvoiceReady(PackagePayment $payment): void
    {
        $owner = $payment->shop->owner;
        $pdfUrl = $payment->invoicePdfUrl();

        if ($owner === null || $pdfUrl === null) {
            return;
        }

        $number = filled($payment->invoice_number) ? ' nr '.$payment->invoice_number : '';

        EmailMessage::create([
            'priority' => MailPriority::Mid,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Faktura'.$number.' za pakiet — Kramio',
            'preheader' => 'Twoja faktura za pakiet jest gotowa.',
            'heading' => 'Faktura za pakiet',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Do opłaty za pakiet **'.config("shop.packages.{$payment->target_package}.name").'** wystawiliśmy fakturę'
                        .($number !== '' ? ' **'.trim($number).'**' : '').'.',
                    'Kwota: **'.Money::pln($payment->amount).'**.',
                ],
            ],
            'action_text' => 'Pobierz fakturę',
            'action_url' => $pdfUrl,
            'outro_lines' => [
                'Masz pytania do faktury? Odpowiedz na tę wiadomość.',
            ],
        ]);
    }

    /**
     * Mail po rozpoczęciu zakupu — z linkiem do bramki na wypadek, gdyby coś
     * przerwało płatność (zamknięta karta, zerwane połączenie). Maile pakietów
     * idą OD PLATFORMY (bez `shop_id` i brandingu sklepu): to nasza relacja ze
     * sprzedawcą, nie sklepu z jego klientem.
     */
    private function mailPaymentStarted(Shop $shop, PackagePayment $payment, string $redirectUrl): void
    {
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        $packageName = config("shop.packages.{$payment->target_package}.name");

        EmailMessage::create([
            'priority' => MailPriority::Mid,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Zamówienie pakietu '.$packageName.' — Kramio',
            'preheader' => 'Dokończ płatność, jeśli coś przerwało zakup.',
            'heading' => 'Zamówiłeś pakiet '.$packageName,
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Przyjęliśmy Twoje zamówienie pakietu **'.$packageName.'** dla sklepu **'.$shop->name.'**.',
                    'Do zapłaty: **'.Money::pln($payment->amount).'**'
                        .((float) $payment->credit > 0 ? ' (rok w nowym pakiecie minus '.Money::pln($payment->credit).' zniżki za niewykorzystany okres).' : ' za rok.'),
                ],
                ['Jeśli płatność przebiegła bez przeszkód, nic więcej nie musisz robić — pakiet włączy się sam po potwierdzeniu wpłaty i dostaniesz osobną wiadomość.'],
            ],
            'action_text' => 'Dokończ płatność',
            'action_url' => $redirectUrl,
            'outro_lines' => [
                'Przycisk przyda się, gdyby coś przerwało zakup — prowadzi z powrotem do bramki płatności.',
            ],
        ]);
    }

    /**
     * Mail po włączeniu pakietu: podziękowanie + wypunktowane funkcje (czytane
     * z uprawnień SKLEPU, więc lista obejmuje też ręczne nadania) + termin.
     */
    private function mailPackageActivated(Shop $shop, PackagePayment $payment): void
    {
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        $packageName = config("shop.packages.{$payment->target_package}.name");

        EmailMessage::create([
            'priority' => MailPriority::Mid,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Pakiet '.$packageName.' jest aktywny — Kramio',
            'preheader' => 'Dziękujemy za zakup! Wszystkie funkcje są już włączone.',
            'heading' => 'Dziękujemy — pakiet '.$packageName.' działa!',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Płatność **'.Money::pln($payment->amount).'** została potwierdzona, a pakiet **'.$packageName.'** jest już aktywny w Twoim sklepie **'.$shop->name.'**.',
                    'Opłacony do: **'.$payment->new_ends_at->format('d.m.Y').'**.',
                ],
                array_merge(
                    ['**Co masz w pakiecie:**'],
                    array_map(fn (string $feature): string => '• '.$feature, PackageFeatures::forShop($shop)),
                ),
            ],
            'action_text' => 'Przejdź do panelu',
            'action_url' => route('seller.package.show'),
            'outro_lines' => [
                'Masz pytania? Po prostu odpowiedz na tę wiadomość.',
            ],
        ]);
    }
}
