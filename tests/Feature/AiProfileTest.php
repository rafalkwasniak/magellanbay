<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiProfile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Wybór modelu per zadanie: kod prosi o ZADANIE, config decyduje, czym je wykonać.
 */
class AiProfileTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /** Sklep, na który liczy się limit AI (tu bez znaczenia — chodzi o payload). */
    private function shop(): Shop
    {
        return $this->sklep ??= Shop::factory()->create();
    }

    private ?Shop $sklep = null;

    private function fakeOk(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'odpowiedź']]],
            ]),
        ]);
    }

    public function test_task_inherits_defaults(): void
    {
        config([
            'ai.defaults' => [
                'provider' => 'deepseek',
                'model' => 'model-domyslny',
                'reasoning_effort' => 'low',
                'temperature' => 0.3,
                'timeout' => 120,
            ],
            'ai.tasks.proofread' => [],
        ]);

        $profile = AiProfile::forTask('proofread');

        $this->assertSame('model-domyslny', $profile->model);
        $this->assertSame('low', $profile->reasoningEffort);
        $this->assertSame(0.3, $profile->temperature);
        $this->assertSame(120, $profile->timeout);
    }

    public function test_task_overrides_only_what_it_declares(): void
    {
        config([
            'ai.defaults' => [
                'provider' => 'deepseek',
                'model' => 'model-domyslny',
                'reasoning_effort' => 'low',
                'temperature' => 0.3,
                'timeout' => 120,
            ],
            'ai.tasks.product_copy' => ['model' => 'model-mocny', 'temperature' => 0.8],
        ]);

        $profile = AiProfile::forTask('product_copy');

        $this->assertSame('model-mocny', $profile->model);
        $this->assertSame(0.8, $profile->temperature);
        // Niezadeklarowane spada na ustawienia domyślne.
        $this->assertSame('low', $profile->reasoningEffort);
        $this->assertSame(120, $profile->timeout);
    }

    public function test_two_tasks_hit_different_models(): void
    {
        config([
            'ai.providers.deepseek.key' => 'test-key',
            'ai.tasks.proofread' => ['model' => 'model-tani'],
            'ai.tasks.product_copy' => ['model' => 'model-mocny'],
        ]);
        $this->fakeOk();

        $client = app(AiClient::class);
        $client->run('proofread', 'instrukcja', 'tekst', $this->shop());
        $client->run('product_copy', 'instrukcja', 'tekst', $this->shop());

        $models = [];
        Http::recorded(function ($request) use (&$models) {
            $models[] = $request->data()['model'];
        });

        $this->assertSame(['model-tani', 'model-mocny'], $models);
    }

    public function test_task_can_use_a_different_provider(): void
    {
        config([
            'ai.providers.inny' => ['base_url' => 'https://api.inny-dostawca.test', 'key' => 'klucz-innego'],
            'ai.tasks.product_copy' => ['provider' => 'inny', 'model' => 'model-innego'],
        ]);
        $this->fakeOk();

        app(AiClient::class)->run('product_copy', 'instrukcja', 'tekst', $this->shop());

        Http::assertSent(fn ($request) => $request->url() === 'https://api.inny-dostawca.test/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer klucz-innego')
            && $request->data()['model'] === 'model-innego');
    }

    public function test_empty_reasoning_effort_is_not_sent(): void
    {
        config([
            'ai.providers.deepseek.key' => 'test-key',
            'ai.tasks.proofread' => ['reasoning_effort' => ''],
        ]);
        $this->fakeOk();

        app(AiClient::class)->run('proofread', 'instrukcja', 'tekst', $this->shop());

        Http::assertSent(fn ($request) => ! array_key_exists('reasoning_effort', $request->data()));
    }

    public function test_unknown_task_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);

        AiProfile::forTask('zadanie-ktorego-nie-ma');
    }

    public function test_unknown_provider_fails_loudly(): void
    {
        config(['ai.tasks.proofread' => ['provider' => 'nieistniejacy']]);

        $this->expectException(RuntimeException::class);

        AiProfile::forTask('proofread');
    }

    public function test_missing_key_means_not_configured(): void
    {
        config(['ai.providers.deepseek.key' => '']);

        $this->assertFalse(AiProfile::forTask('proofread')->isConfigured());
    }

    public function test_shipped_config_defines_the_tasks_the_code_uses(): void
    {
        // Zadania wołane z kodu muszą istnieć w dostarczonym config/ai.php.
        foreach (['proofread', 'product_copy'] as $task) {
            $this->assertIsArray(config("ai.tasks.{$task}"), "Brak zadania AI: {$task}.");
        }
    }
}
