<?php

namespace Tests\Feature\Seller;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyLookupTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::factory()->consented()->create();
    }

    public function test_lookup_fills_company_and_parsed_address(): void
    {
        Http::fake([
            'wl-api.mf.gov.pl/*' => Http::response([
                'result' => [
                    'subject' => [
                        'name' => 'PRZYKŁAD SP. Z O.O.',
                        'residenceAddress' => 'UL. KWIATOWA 1/2, 00-001 WARSZAWA',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->seller())
            ->postJson(route('seller.company.lookup'), ['nip' => '1234563218'])
            ->assertOk()
            ->assertJson([
                'company_name' => 'PRZYKŁAD SP. Z O.O.',
                'street' => 'Kwiatowa',
                'building_number' => '1',
                'apartment_number' => '2',
                'postal_code' => '00-001',
                'city' => 'Warszawa',
            ]);
    }

    public function test_invalid_nip_is_rejected(): void
    {
        Http::fake();

        $this->actingAs($this->seller())
            ->postJson(route('seller.company.lookup'), ['nip' => '123'])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_company_not_found_returns_404(): void
    {
        Http::fake([
            'wl-api.mf.gov.pl/*' => Http::response(['result' => ['subject' => null]]),
        ]);

        $this->actingAs($this->seller())
            ->postJson(route('seller.company.lookup'), ['nip' => '1234563218'])
            ->assertStatus(404);
    }
}
