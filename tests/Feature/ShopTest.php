<?php

namespace Tests\Feature;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_falls_back_to_subdomain_when_no_custom_domain(): void
    {
        config(['tenancy.central_domain' => 'shop.test']);
        $shop = Shop::factory()->create(['slug' => 'wiazanki-malgosi', 'domain' => null]);

        $this->assertSame('wiazanki-malgosi.shop.test', $shop->host());
    }

    public function test_host_prefers_dedicated_domain_when_set(): void
    {
        config(['tenancy.central_domain' => 'shop.test']);
        $shop = Shop::factory()->create(['slug' => 'wiazanki-malgosi', 'domain' => 'mojsklep.pl']);

        $this->assertSame('mojsklep.pl', $shop->host());
    }
}
