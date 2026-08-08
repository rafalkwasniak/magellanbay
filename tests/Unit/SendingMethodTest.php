<?php

namespace Tests\Unit;

use App\Enums\SendingMethod;
use PHPUnit\Framework\TestCase;

/**
 * Sposób nadania przesyłki. Wartości enumu jadą wprost do ShipX jako
 * `custom_attributes.sending_method`, więc literówka nie wywali się u nas —
 * InPost po prostu odda ofertę jako niedostępną.
 */
class SendingMethodTest extends TestCase
{
    public function test_wartosci_odpowiadaja_temu_czego_oczekuje_shipx(): void
    {
        $this->assertSame('parcel_locker', SendingMethod::ParcelLocker->value);
        $this->assertSame('dispatch_order', SendingMethod::DispatchOrder->value);
    }

    public function test_domyslny_sposob_nadania_jest_darmowy(): void
    {
        // Odbiór kuriera jest dodatkowo płatny — nie może włączyć się sam.
        $this->assertSame(SendingMethod::ParcelLocker, SendingMethod::default());
        $this->assertFalse(SendingMethod::default()->isPaid());
    }

    public function test_tylko_odbior_kuriera_jest_platny(): void
    {
        $this->assertTrue(SendingMethod::DispatchOrder->isPaid());
        $this->assertFalse(SendingMethod::ParcelLocker->isPaid());
    }

    public function test_kazdy_sposob_ma_etykiete_i_podpowiedz(): void
    {
        foreach (SendingMethod::cases() as $case) {
            $this->assertNotSame('', trim($case->label()));
            $this->assertNotSame('', trim($case->hint()));
        }
    }
}
