<?php

namespace Tests\Unit;

use App\Enums\VatRate;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductPriceTest extends TestCase
{
    public function test_net_and_vat_are_derived_from_gross(): void
    {
        $product = new Product(['price_gross' => 123.00, 'vat_rate' => VatRate::R23]);

        $this->assertSame(100.0, $product->priceNet());
        $this->assertSame(23.0, $product->vatAmount());
    }

    public function test_exempt_rate_has_no_vat(): void
    {
        $product = new Product(['price_gross' => 80.00, 'vat_rate' => VatRate::Zw]);

        $this->assertSame(80.0, $product->priceNet());
        $this->assertSame(0.0, $product->vatAmount());
    }

    public function test_in_stock_logic(): void
    {
        $tracked = new Product(['track_stock' => true, 'stock' => 0]);
        $unlimited = new Product(['track_stock' => false, 'stock' => null]);

        $this->assertFalse($tracked->inStock());
        $this->assertTrue($unlimited->inStock());
    }
}
