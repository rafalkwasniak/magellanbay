<?php

namespace Tests\Feature\Seller;

use App\Jobs\GenerateSeoDescription;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\SeoDescriptionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Opis SEO pisany przez AI: job po zmianie treści + przycisk „Wygeneruj z AI".
 *
 * Reguły, których pilnujemy: ręczny tekst jest nietykalny, za krótka treść nie
 * idzie do modelu (ogólnik nie jest lepszy od zdania z faktów), a awaria AI nie
 * może zepsuć ani zapisu produktu, ani strony.
 */
class SeoDescriptionAiTest extends TestCase
{
    use RefreshDatabase;

    private const LONG_TEXT = '<p>Karbonowa rama, grupa Shimano Ultegra Di2, koła 50 mm i opony 28 mm. '
        .'Rower zbudowany na długie dystanse i szybkie treningi w terenie pagórkowatym.</p>';

    private function fakeAi(string $answer): void
    {
        $this->mock(AiClient::class, function ($mock) use ($answer) {
            $mock->shouldReceive('run')->andReturn($answer);
        });
    }

    public function test_job_writes_the_description_from_the_product_text(): void
    {
        $this->fakeAi('  "Karbonowa szosa z grupą Ultegra Di2 — gotowa na długie dystanse."  ');

        $product = Product::factory()->create(['description' => self::LONG_TEXT]);

        (new GenerateSeoDescription($product))->handle(app(SeoDescriptionWriter::class));

        $product->refresh();
        // Cudzysłowy i białe znaki sprząta KOD, nie prośba w prompcie.
        $this->assertSame('Karbonowa szosa z grupą Ultegra Di2 — gotowa na długie dystanse.', $product->meta_description);
        $this->assertFalse($product->meta_description_manual);
    }

    public function test_job_never_touches_a_manual_description(): void
    {
        $this->fakeAi('Opis od AI');

        $product = Product::factory()->create([
            'description' => self::LONG_TEXT,
            'meta_description' => 'Mój własny opis',
            'meta_description_manual' => true,
        ]);

        (new GenerateSeoDescription($product))->handle(app(SeoDescriptionWriter::class));

        $this->assertSame('Mój własny opis', $product->fresh()->meta_description);
    }

    public function test_too_short_a_text_does_not_go_to_the_model(): void
    {
        $this->mock(AiClient::class, function ($mock) {
            // Model nie ma czego streszczać — nie wolno go w ogóle wołać.
            $mock->shouldNotReceive('run');
        });

        $product = Product::factory()->create(['description' => '<p>Ładny rower.</p>']);

        (new GenerateSeoDescription($product))->handle(app(SeoDescriptionWriter::class));

        $this->assertNull($product->fresh()->meta_description);
    }

    public function test_ai_failure_leaves_the_product_alone(): void
    {
        $this->mock(AiClient::class, function ($mock) {
            $mock->shouldReceive('run')->andThrow(new RuntimeException('DeepSeek leży'));
        });

        $product = Product::factory()->create(['description' => self::LONG_TEXT]);

        // Brak opisu to nie awaria — zostaje deterministyczne zdanie z faktów.
        (new GenerateSeoDescription($product))->handle(app(SeoDescriptionWriter::class));

        $this->assertNull($product->fresh()->meta_description);
    }

    public function test_changing_the_description_queues_the_job(): void
    {
        $product = Product::factory()->create();
        Bus::fake();

        $product->update(['description' => self::LONG_TEXT]);

        Bus::assertDispatched(GenerateSeoDescription::class);
    }

    public function test_saving_without_touching_the_text_queues_nothing(): void
    {
        $product = Product::factory()->create(['description' => self::LONG_TEXT]);
        Bus::fake();

        // Zmiana ceny nie ma wpływu na opis — wywołanie AI byłoby spalonym tokenem.
        $product->update(['price_gross' => 199.99]);

        Bus::assertNotDispatched(GenerateSeoDescription::class);
    }

    public function test_manual_description_blocks_queueing_altogether(): void
    {
        $product = Product::factory()->create(['meta_description_manual' => true]);
        Bus::fake();

        $product->update(['description' => self::LONG_TEXT]);

        Bus::assertNotDispatched(GenerateSeoDescription::class);
    }

    public function test_button_returns_text_without_saving_it(): void
    {
        $this->fakeAi('Opis napisany na żądanie sprzedawcy.');

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $response = $this->actingAs($seller)->postJson(route('ai.seo-description'), [
            'text' => self::LONG_TEXT,
            'name' => 'Trek Madone SL7',
        ])->assertOk();

        $this->assertSame('Opis napisany na żądanie sprzedawcy.', $response->json('text'));
    }

    public function test_button_refuses_when_there_is_nothing_to_summarise(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)->postJson(route('ai.seo-description'), [
            'text' => '<p>Krótko.</p>',
        ])->assertStatus(422)->assertJsonPath('message', 'Za mało treści, aby napisać opis. Uzupełnij opis i spróbuj ponownie.');
    }

    public function test_button_reports_an_unavailable_service_instead_of_crashing(): void
    {
        $this->mock(AiClient::class, function ($mock) {
            $mock->shouldReceive('run')->andThrow(new RuntimeException('brak klucza'));
        });

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);

        $this->actingAs($seller)->postJson(route('ai.seo-description'), ['text' => self::LONG_TEXT])
            ->assertStatus(503);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
