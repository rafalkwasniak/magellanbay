<?php

namespace Tests\Unit;

use App\Services\NipService;
use PHPUnit\Framework\TestCase;

class NipServiceTest extends TestCase
{
    private NipService $nip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nip = new NipService;
    }

    public function test_normalize_strips_non_digits(): void
    {
        $this->assertSame('1234563218', $this->nip->normalize('123-456-32-18'));
        $this->assertSame('1234563218', $this->nip->normalize('PL 123 456 32 18'));
    }

    public function test_normalize_returns_null_when_empty(): void
    {
        $this->assertNull($this->nip->normalize(''));
        $this->assertNull($this->nip->normalize(null));
    }

    public function test_valid_nip_passes_checksum(): void
    {
        $this->assertTrue($this->nip->isValid('1234563218'));
    }

    public function test_invalid_checksum_fails(): void
    {
        $this->assertFalse($this->nip->isValid('1234567890'));
    }

    public function test_wrong_length_fails(): void
    {
        $this->assertFalse($this->nip->isValid('123'));
        $this->assertFalse($this->nip->isValid('12345632180'));
    }
}
