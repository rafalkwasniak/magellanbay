<?php

namespace Tests\Feature\Seller;

use App\Livewire\Administrator\ShopManager;
use App\Models\PackageChange;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Historia pakietu obejmuje OBIE drogi zmiany: opłatę sprzedawcy i ręczne
 * nadanie z konsoli admina. Bez logu ręcznie nadany pakiet wyglądał w panelu,
 * jakby wziął się z powietrza — historia pokazywała tylko płatności.
 */
class PackageHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();

        return [$seller, Shop::factory()->withInvoiceData()->create(['owner_id' => $seller->id])];
    }

    public function test_manual_grant_from_the_admin_console_lands_in_the_history(): void
    {
        $admin = User::factory()->consented()->create(['role' => 'admin']);
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($admin);
        Livewire::test(ShopManager::class, ['shop' => $shop])
            ->call('applyPreset', 'pavilion')
            ->set('subscription_ends_at', '2027-01-31')
            ->call('save');

        $change = $shop->packageChanges()->firstOrFail();
        $this->assertSame('pavilion', $change->package);
        $this->assertSame(PackageChange::SOURCE_ADMIN, $change->source);
        $this->assertNull($change->package_payment_id);
        $this->assertSame('2027-01-31', $change->ends_at->format('Y-m-d'));

        // Sprzedawca widzi, skąd ma pakiet — bez kwoty, bo płatności nie było.
        $this->actingAs($seller->fresh())->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Historia pakietu')
            ->assertSee('nadany przez Kramio')
            ->assertSee('ważny do 31.01.2027');
    }

    public function test_entitlement_only_change_does_not_pollute_the_history(): void
    {
        $admin = User::factory()->consented()->create(['role' => 'admin']);
        [, $shop] = $this->sellerWithShop();

        $this->actingAs($admin);

        // Pierwszy zapis: nadanie pakietu → wpis.
        Livewire::test(ShopManager::class, ['shop' => $shop])->call('applyPreset', 'booth')->call('save');
        $this->assertSame(1, $shop->packageChanges()->count());

        // Drugi zapis rusza TYLKO uprawnienie — historia pakietu milczy.
        Livewire::test(ShopManager::class, ['shop' => $shop->fresh()])
            ->set('bulk_mail', true)
            ->call('save');

        $this->assertSame(1, $shop->packageChanges()->count());
        $this->assertTrue($shop->fresh()->rawEntitlement('bulk_mail'));
    }

    public function test_history_keeps_the_order_and_marks_the_newest_as_current(): void
    {
        $admin = User::factory()->consented()->create(['role' => 'admin']);
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($admin);
        Carbon::setTestNow(Carbon::parse('2026-05-01 10:00'));
        Livewire::test(ShopManager::class, ['shop' => $shop])->call('applyPreset', 'pavilion')->call('save');

        Carbon::setTestNow(Carbon::parse('2026-07-30 10:00'));
        Livewire::test(ShopManager::class, ['shop' => $shop->fresh()])->call('applyPreset', 'stall')->call('save');

        // Najnowszy wpis pierwszy i tylko on jest „obecny".
        $changes = $shop->packageChanges()->get();
        $this->assertSame(['stall', 'pavilion'], $changes->pluck('package')->all());

        $html = $this->actingAs($seller->fresh())->get(route('seller.package.show'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '>obecny<'));
        // Kram stoi wyżej niż Pawilon — kolejność chronologiczna od najnowszego.
        $this->assertLessThan(mb_strpos($html, 'Pawilon</span>'), mb_strpos($html, 'Kram'));

        Carbon::setTestNow();
    }

    public function test_comped_access_is_described_instead_of_a_date(): void
    {
        $admin = User::factory()->consented()->create(['role' => 'admin']);
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($admin);
        Livewire::test(ShopManager::class, ['shop' => $shop])
            ->call('applyPreset', 'pavilion')
            ->set('comped', true)
            ->call('save');

        $this->actingAs($seller->fresh())->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('dostęp bezpłatny');
    }
}
