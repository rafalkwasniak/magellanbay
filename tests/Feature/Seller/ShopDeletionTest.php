<?php

namespace Tests\Feature\Seller;

use App\Models\EmailMessage;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Samoobsługowe usunięcie własnego sklepu przez sprzedawcę: rachunek strat,
 * dwa bezpieczniki (nazwa + hasło), karencja i droga odwrotu.
 */
class ShopDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function seller(array $shopAttributes = []): array
    {
        $user = User::factory()->consented()->create(['password' => Hash::make('Tajne-Haslo1')]);
        $shop = Shop::factory()->for($user, 'owner')->create($shopAttributes);

        return [$user, $shop];
    }

    /**
     * @return array<string, string>
     */
    private function payload(Shop $shop, array $overrides = []): array
    {
        return array_merge([
            'confirm_name' => $shop->name,
            'current_password' => 'Tajne-Haslo1',
        ], $overrides);
    }

    public function test_screen_shows_what_will_be_lost(): void
    {
        [$user, $shop] = $this->seller(['name' => 'Kwiatki Anny']);
        Product::factory()->count(3)->for($shop)->create();

        $this->actingAs($user)
            ->get(route('seller.deletion.show'))
            ->assertOk()
            ->assertSee('Kwiatki Anny')
            ->assertSee('3 produkty')
            ->assertSee('Wpisz nazwę swojego sklepu');
    }

    public function test_screen_warns_about_a_paid_package(): void
    {
        [$user, $shop] = $this->seller();
        $shop->forceFill([
            'package' => 'booth',
            'subscription_ends_at' => now()->addMonths(9),
            'comped' => false,
        ])->save();

        $this->actingAs($user)
            ->get(route('seller.deletion.show'))
            ->assertOk()
            ->assertSee('nie zostanie zwrócona');
    }

    public function test_seller_schedules_deletion_and_shop_goes_dark(): void
    {
        [$user, $shop] = $this->seller(['name' => 'Kwiatki Anny']);

        $this->actingAs($user)
            ->post(route('seller.deletion.store'), $this->payload($shop))
            ->assertRedirect(route('seller.deletion.show'));

        $shop->refresh();

        $this->assertNotNull($shop->deletion_scheduled_at);
        $this->assertTrue(
            $shop->deletion_scheduled_at->isSameDay(now()->addDays(config('shop.deletion.grace_days'))),
        );

        // Nic jeszcze nie zostało skasowane — karencja to wyłącznie zgaszenie sklepu.
        $this->assertDatabaseHas('shops', ['id' => $shop->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_storefront_stops_answering_during_the_grace_period(): void
    {
        [, $shop] = $this->seller();
        Product::factory()->for($shop)->create();

        $host = $shop->slug.'.'.config('tenancy.central_domain');

        $this->get('http://'.$host)->assertOk();

        $shop->forceFill(['deletion_scheduled_at' => now()->addDays(7)])->save();

        $this->get('http://'.$host)->assertNotFound();
    }

    public function test_wrong_password_does_not_schedule_anything(): void
    {
        [$user, $shop] = $this->seller();

        $this->actingAs($user)
            ->post(route('seller.deletion.store'), $this->payload($shop, ['current_password' => 'nie-to-haslo']))
            ->assertSessionHasErrors('current_password');

        $this->assertNull($shop->fresh()->deletion_scheduled_at);
    }

    public function test_wrong_shop_name_does_not_schedule_anything(): void
    {
        [$user, $shop] = $this->seller(['name' => 'Kwiatki Anny']);

        $this->actingAs($user)
            ->post(route('seller.deletion.store'), $this->payload($shop, ['confirm_name' => 'Kwiatki']))
            ->assertSessionHasErrors('confirm_name');

        $this->assertNull($shop->fresh()->deletion_scheduled_at);
    }

    public function test_scheduling_queues_an_email_with_the_date(): void
    {
        [$user, $shop] = $this->seller();

        $this->actingAs($user)->post(route('seller.deletion.store'), $this->payload($shop));

        $message = EmailMessage::where('to_email', $user->email)->firstOrFail();

        $this->assertStringContainsString(
            $shop->fresh()->deletion_scheduled_at->format('d.m.Y'),
            $message->subject,
        );
    }

    public function test_seller_can_stop_the_deletion(): void
    {
        [$user, $shop] = $this->seller();
        $shop->forceFill(['deletion_scheduled_at' => now()->addDays(5)])->save();

        $this->actingAs($user)
            ->post(route('seller.deletion.cancel'))
            ->assertRedirect(route('seller.shop.edit'));

        $this->assertNull($shop->fresh()->deletion_scheduled_at);
    }

    public function test_panel_banner_offers_the_way_back(): void
    {
        [$user, $shop] = $this->seller();
        $shop->forceFill(['deletion_scheduled_at' => now()->addDays(5)])->save();

        $this->actingAs($user)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Usuniemy Twój sklep')
            ->assertSee('zatrzymaj usunięcie');
    }

    /**
     * Ekran po zleceniu pokazuje WYŁĄCZNIE drogę odwrotu — powtarzanie rachunku
     * strat i formularza przy podjętej decyzji tylko myli.
     */
    public function test_screen_after_scheduling_shows_only_the_way_back(): void
    {
        [$user, $shop] = $this->seller();
        $shop->forceFill(['deletion_scheduled_at' => now()->addDays(5)])->save();

        $this->actingAs($user)
            ->get(route('seller.deletion.show'))
            ->assertOk()
            ->assertSee('Zatrzymaj usunięcie')
            ->assertDontSee('Wpisz nazwę swojego sklepu');
    }

    public function test_seller_cannot_touch_another_sellers_shop(): void
    {
        [, $victimShop] = $this->seller(['name' => 'Kwiatki Anny']);
        [$intruder, $intruderShop] = $this->seller(['name' => 'Bukiety Bartka']);

        $this->actingAs($intruder)
            ->post(route('seller.deletion.store'), [
                'confirm_name' => 'Kwiatki Anny',
                'current_password' => 'Tajne-Haslo1',
            ])
            ->assertSessionHasErrors('confirm_name');

        $this->assertNull($victimShop->fresh()->deletion_scheduled_at);
        $this->assertNull($intruderShop->fresh()->deletion_scheduled_at);
    }

    public function test_admin_has_no_seller_deletion_screen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('seller.deletion.show'))
            ->assertForbidden();
    }

    /**
     * Pełna droga: zlecenie → karencja mija → `shops:purge` kasuje sklep i konto.
     */
    public function test_purge_finishes_the_job_after_the_grace_period(): void
    {
        [$user, $shop] = $this->seller();

        $this->actingAs($user)->post(route('seller.deletion.store'), $this->payload($shop));

        $this->travel(config('shop.deletion.grace_days') + 1)->days();

        $this->artisan('shops:purge')->assertSuccessful();

        $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('reserved_slugs', ['slug' => $shop->slug]);
    }
}
