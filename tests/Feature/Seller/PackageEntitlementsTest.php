<?php

namespace Tests\Feature\Seller;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_package_snapshots_entitlements_from_config(): void
    {
        $shop = Shop::factory()->create();

        $shop->assignPackage('pavilion');

        $fresh = $shop->fresh();
        $this->assertSame('pavilion', $fresh->package);
        $this->assertSame(96, $fresh->entitlement('max_products'));
        $this->assertTrue($fresh->entitlement('custom_domain'));
    }

    public function test_assign_unknown_package_throws(): void
    {
        $shop = Shop::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $shop->assignPackage('nieistniejacy');
    }

    public function test_entitlement_reads_snapshot_not_current_config(): void
    {
        // Sklep kupił „stall" z limitem 24 (snapshot na sklepie).
        $shop = Shop::factory()->package('stall')->create();

        // Później zmieniamy definicję pakietu w configu — snapshot NIE drgnie.
        config(['shop.packages.stall.entitlements.max_products' => 999]);

        $this->assertSame(24, $shop->entitlement('max_products'));
    }

    public function test_entitlement_falls_back_to_config_when_not_in_snapshot(): void
    {
        // Sklep bez snapshotu (legacy) — resolver sięga do configu aktualnego pakietu.
        $shop = Shop::factory()->create(['package' => 'booth', 'entitlements' => null]);

        $this->assertSame(48, $shop->entitlement('max_products'));
        $this->assertTrue($shop->entitlement('online_payments'));
    }

    public function test_entitlement_falls_back_for_key_missing_from_snapshot(): void
    {
        // Nowe uprawnienie dodane do configu po zakupie — brak go w snapshocie,
        // więc resolver bierze wartość z definicji pakietu.
        $shop = Shop::factory()->create([
            'package' => 'stall',
            'entitlements' => ['max_products' => 24], // stary, niepełny snapshot
        ]);
        config(['shop.packages.stall.entitlements.new_feature' => true]);

        $this->assertTrue($shop->entitlement('new_feature'));
    }

    public function test_package_name_resolves_polish_label_from_slug(): void
    {
        $shop = Shop::factory()->package('booth')->create();

        $this->assertSame('Stragan', $shop->packageName());

        // Nazwa zmienialna z configu bez ruszania sluga w bazie.
        config(['shop.packages.booth.name' => 'Bazar']);
        $this->assertSame('Bazar', $shop->fresh()->packageName());
        $this->assertSame('booth', $shop->fresh()->package);
    }

    public function test_factory_materializes_default_package_snapshot(): void
    {
        $shop = Shop::factory()->create();

        $this->assertSame('stall', $shop->package);
        $this->assertSame(24, $shop->entitlement('max_products'));
        $this->assertFalse($shop->entitlement('online_payments'));
    }
}
