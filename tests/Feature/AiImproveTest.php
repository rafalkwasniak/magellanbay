<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiImproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_improve_text(): void
    {
        config(['services.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Poprawiony tekst.']]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();

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
        config(['services.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => "```html\n<div>Poprawiony <strong>opis</strong></div>\n```"]]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();

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
        config(['services.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '<div>Poprawiony <strong>opis</strong></div>']]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();

        $this->actingAs($seller)
            ->postJson(route('ai.improve'), [
                'field' => 'product_description',
                'text' => '<div>opis</div>',
            ])
            ->assertOk()
            ->assertJson(['text' => '<div>Poprawiony <strong>opis</strong></div>']);
    }

    public function test_unconfigured_service_returns_503(): void
    {
        config(['services.deepseek.key' => '']);
        Http::fake();

        $seller = User::factory()->consented()->create();

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
