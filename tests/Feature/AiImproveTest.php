<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiImproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_improve_text(): void
    {
        config(['ai.providers.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Poprawiony tekst.']]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'shop_description',
                'text' => 'tekst do poprawy',
            ])
            ->assertOk()
            ->assertJson(['text' => 'Poprawiony tekst.']);
    }

    public function test_html_field_preserves_markup_and_strips_code_fences(): void
    {
        config(['ai.providers.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => "```html\n<div>Poprawiony <strong>opis</strong></div>\n```"]]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'shop_description',
                'text' => '<div>opis</div>',
            ])
            ->assertOk()
            ->assertJson(['text' => '<div>Poprawiony <strong>opis</strong></div>']);
    }

    public function test_product_description_uses_html_mode(): void
    {
        config(['ai.providers.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '<div>Poprawiony <strong>opis</strong></div>']]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'product_description',
                'text' => '<div>opis</div>',
            ])
            ->assertOk()
            ->assertJson(['text' => '<div>Poprawiony <strong>opis</strong></div>']);
    }

    public function test_output_limit_follows_the_chunk_not_the_whole_field(): void
    {
        // Przychodzi FRAGMENT (dzieli przeglądarka), więc instrukcja dla modelu
        // musi ograniczać wynik do rozmiaru fragmentu. Gdyby szła tu długość
        // całego pola (strona CMS: 30 tys. znaków), model dostawałby przyzwolenie
        // na rozdmuchanie krótkiego akapitu w wypracowanie.
        config(['ai.providers.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '<div>ok</div>']]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie
        $chunk = '<div>'.str_repeat('a', 289).'</div>'; // 300 znaków → limit 390

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), ['field' => 'page_content', 'text' => $chunk])
            ->assertOk();

        Http::assertSent(function ($request) {
            $system = $request->data()['messages'][0]['content'];

            return str_contains($system, 'nie może przekroczyć 390 znaków')
                && ! str_contains($system, '30000');
        });
    }

    public function test_short_chunk_gets_a_sane_minimum_limit(): void
    {
        // Kilkuznakowy nagłówek nie może dostać limitu w rodzaju „max 5 znaków",
        // bo poprawiona wersja bywa dłuższa od oryginału.
        config(['ai.providers.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '<h2>Ok</h2>']]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), ['field' => 'page_content', 'text' => '<h2>tytul</h2>'])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains(
            $request->data()['messages'][0]['content'],
            'nie może przekroczyć 200 znaków'
        ));
    }

    public function test_streaming_emits_deltas_and_final_text_as_sse(): void
    {
        config(['ai.providers.deepseek.key' => 'test-key', 'ai.streaming' => true]);
        // Odpowiedź modelu w formacie strumienia (SSE): dwa kawałki i koniec.
        Http::fake([
            '*/chat/completions' => Http::response(
                'data: {"choices":[{"delta":{"content":"Popra"}}]}'."\n\n"
                .'data: {"choices":[{"delta":{"content":"wione."}}]}'."\n\n"
                ."data: [DONE]\n\n",
            ),
        ]);

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie

        $response = $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'shop_description',
                'text' => '<div>tekst do poprawy</div>',
                'stream' => true,
            ]);

        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

        $content = $response->streamedContent();

        // Kawałki po drodze + zdarzenie końcowe z pełnym tekstem i stanem puli.
        $this->assertStringContainsString('data: {"delta":"Popra"}', $content);
        $this->assertStringContainsString('data: {"delta":"wione."}', $content);
        $this->assertStringContainsString('"done":true', $content);
        $this->assertStringContainsString('"text":"Poprawione."', $content);
        $this->assertStringContainsString('"remaining"', $content);
    }

    public function test_streaming_request_falls_back_to_json_when_flag_is_off(): void
    {
        // Wyłącznik awaryjny AI_STREAMING=false: front może prosić o strumień,
        // ale serwer odpowiada sprawdzonym JSON-em — i front to honoruje
        // (rozpoznanie po Content-Type, nie po tym, o co prosił).
        config(['ai.providers.deepseek.key' => 'test-key', 'ai.streaming' => false]);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Poprawiony tekst.']]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'shop_description',
                'text' => 'tekst do poprawy',
                'stream' => true,
            ])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['text' => 'Poprawiony tekst.']);
    }

    public function test_streaming_reports_exhausted_quota_inside_the_stream(): void
    {
        // Nagłówki strumienia (200) wychodzą przed pobraniem limitu, więc 429
        // nie ma jak wrócić statusem HTTP — jedzie zdarzeniem w strumieniu,
        // z tą samą treścią, którą dostaje ścieżka JSON.
        config(['ai.providers.deepseek.key' => 'test-key', 'ai.streaming' => true]);
        Http::fake();

        $seller = User::factory()->consented()->create();
        Shop::factory()->create([
            'owner_id' => $seller->id,
            'entitlements' => ['ai_weekly_limit' => 0],  // pula wyczerpana od startu
        ]);

        $response = $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'shop_description',
                'text' => 'tekst do poprawy',
                'stream' => true,
            ]);

        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('"error":true', $content);
        $this->assertStringContainsString('"status":429', $content);
        $this->assertStringContainsString('pulę AI', $content);
    }

    public function test_unconfigured_service_returns_503(): void
    {
        config(['ai.providers.deepseek.key' => '']);
        Http::fake();

        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'shop_description',
                'text' => 'tekst do poprawy',
            ])
            ->assertStatus(503);
    }

    public function test_invalid_field_is_rejected(): void
    {
        $seller = User::factory()->consented()->create();
        Shop::factory()->create(['owner_id' => $seller->id]);   // limit AI wisi na sklepie

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'nieistniejace_pole',
                'text' => 'tekst',
            ])
            ->assertStatus(422);
    }

    public function test_guest_cannot_use_ai(): void
    {
        $this->postJson(route('ai.improve'), [
            'field' => 'shop_description',
            'text' => 'tekst',
        ])->assertUnauthorized();
    }
}
