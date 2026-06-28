<?php

namespace Tests\Unit;

use App\Services\TagNormalizer;
use PHPUnit\Framework\TestCase;

class TagNormalizerTest extends TestCase
{
    private TagNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new TagNormalizer;
    }

    public function test_normalizes_single_tag(): void
    {
        $this->assertSame('ślub', $this->normalizer->normalize('Ślub'));
        $this->assertSame('czarno-niebieskie', $this->normalizer->normalize('czarno - niebieskie'));
        $this->assertSame('wenecja', $this->normalizer->normalize(',,  Wenecja ,,'));
        $this->assertNull($this->normalizer->normalize('   '));
        $this->assertNull($this->normalizer->normalize(',,'));
    }

    public function test_parses_dedupes_and_sorts_polish(): void
    {
        $this->assertSame(
            ['algorytm', 'ślub', 'wenecja'],
            $this->normalizer->parse(',,ślub ,, wenecja,Algorytm'),
        );
    }

    public function test_parse_dedupes_case_and_hyphen_variants(): void
    {
        $this->assertSame(
            ['czarno-niebieskie'],
            $this->normalizer->parse('czarno - niebieskie, Czarno-Niebieskie'),
        );
    }
}
