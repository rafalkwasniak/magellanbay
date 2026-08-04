<?php

namespace Tests\Feature\Administrator;

use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — usuwanie sklepu (natychmiast, bez karencji) i zatrzymanie
 * usunięcia zleconego przez sprzedawcę.
 */
class ShopDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_deletes_shop_after_typing_its_name(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['name' => 'Kwiatki Anny']);
        $owner = $shop->owner;

        $this->actingAs($admin)
            ->post(route('administrator.shops.destroy', $shop), ['confirm_name' => 'Kwiatki Anny'])
            ->assertRedirect(route('administrator.shops.index'));

        $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('reserved_slugs', ['slug' => $shop->slug]);
    }

    /**
     * Nazwa sklepu jest jedynym bezpiecznikiem tej ścieżki — musi realnie
     * blokować, a nie tylko straszyć.
     */
    public function test_wrong_name_does_not_delete_anything(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['name' => 'Kwiatki Anny']);

        $this->actingAs($admin)
            ->post(route('administrator.shops.destroy', $shop), ['confirm_name' => 'Kwiatki'])
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseHas('shops', ['id' => $shop->id]);
    }

    public function test_missing_name_does_not_delete_anything(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();

        $this->actingAs($admin)
            ->post(route('administrator.shops.destroy', $shop), [])
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseHas('shops', ['id' => $shop->id]);
    }

    /**
     * Wielkość liter i podwójne spacje nie są bezpiecznikiem — całą nazwę i tak
     * trzeba wpisać.
     */
    public function test_name_check_ignores_case_and_extra_spaces(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['name' => 'Kwiatki Anny']);

        $this->actingAs($admin)
            ->post(route('administrator.shops.destroy', $shop), ['confirm_name' => '  kwiatki   anny '])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
    }

    public function test_seller_cannot_delete_a_shop(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['name' => 'Kwiatki Anny']);

        $this->actingAs($seller)
            ->post(route('administrator.shops.destroy', $shop), ['confirm_name' => 'Kwiatki Anny'])
            ->assertForbidden();

        $this->assertDatabaseHas('shops', ['id' => $shop->id]);
    }

    public function test_guest_cannot_delete_a_shop(): void
    {
        $shop = Shop::factory()->create(['name' => 'Kwiatki Anny']);

        $this->post(route('administrator.shops.destroy', $shop), ['confirm_name' => 'Kwiatki Anny'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('shops', ['id' => $shop->id]);
    }

    public function test_admin_stops_a_deletion_scheduled_by_the_seller(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        $shop->forceFill(['deletion_scheduled_at' => now()->addDays(5)])->save();

        $this->actingAs($admin)
            ->from(route('administrator.shops.edit', $shop))
            ->post(route('administrator.shops.restore', $shop))
            ->assertRedirect(route('administrator.shops.edit', $shop));

        $this->assertNull($shop->fresh()->deletion_scheduled_at);

        // Właściciel musi wiedzieć, że ktoś zatrzymał jego zlecenie.
        $this->assertDatabaseHas('email_messages', ['to_email' => $shop->owner->email]);
    }

    public function test_editor_shows_the_deletion_notice_and_stop_button(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        $shop->forceFill(['deletion_scheduled_at' => now()->addDays(5)])->save();

        $this->actingAs($admin)
            ->get(route('administrator.shops.edit', $shop))
            ->assertOk()
            ->assertSee('Sprzedawca zlecił usunięcie tego sklepu')
            ->assertSee('Zatrzymaj usunięcie');
    }

    public function test_shop_list_marks_shops_awaiting_deletion(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->active()->create();
        $shop->forceFill(['deletion_scheduled_at' => now()->addDays(5)])->save();

        $this->actingAs($admin)
            ->get(route('administrator.shops.index'))
            ->assertOk()
            ->assertSee('usunięcie '.$shop->deletion_scheduled_at->format('d.m'));
    }

    /**
     * Ścieżka admina omija karencję — sklep ma zniknąć w chwili kliknięcia,
     * nie za tydzień.
     */
    public function test_admin_deletion_is_immediate_not_scheduled(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['name' => 'Kwiatki Anny']);

        $this->actingAs($admin)
            ->post(route('administrator.shops.destroy', $shop), ['confirm_name' => 'Kwiatki Anny']);

        $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
        $this->assertSame(0, EmailMessage::where('shop_id', $shop->id)->count());
    }
}
