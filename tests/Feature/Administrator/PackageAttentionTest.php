<?php

namespace Tests\Feature\Administrator;

use App\Models\PackagePayment;
use App\Models\Shop;
use App\Models\User;
use App\Support\PackageAttention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — sekcja „Wymaga uwagi" w dziale Pakiety.
 *
 * Ekran ma sens tylko wtedy, gdy sklep trafia do DOKŁADNIE JEDNEJ grupy i gdy
 * milczy o rzeczach, które nie są problemem (gratis, pakiet darmowy, sklep w
 * drodze do usunięcia). Fałszywy alarm na takiej liście jest gorszy niż jej
 * brak — po kilku dniach przestaje się ją czytać.
 */
class PackageAttentionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function groupKeys(): array
    {
        return array_map(fn (array $group): string => $group['key'], PackageAttention::groups());
    }

    public function test_expiring_subscription_is_listed(): void
    {
        $admin = User::factory()->admin()->create();
        Shop::factory()->package('booth')->create([
            'name' => 'Kwiaciarnia Zosia',
            'subscription_ends_at' => now()->addDays(10),
        ]);

        $this->assertSame(['expiring'], $this->groupKeys());

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('Wymaga uwagi')
            ->assertSee('Kończy się wkrótce')
            ->assertSee('Kwiaciarnia Zosia');
    }

    public function test_subscription_far_in_the_future_is_silent(): void
    {
        Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addMonths(6)]);

        $this->assertSame([], PackageAttention::groups());
    }

    public function test_shop_in_grace_lands_only_in_grace_group(): void
    {
        // Sklep po terminie jest zarazem „kończącym się" w potocznym sensie —
        // gdyby wpadł do obu grup, ta sama sprawa wyglądałaby na dwie.
        Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->subDays(2)]);

        $this->assertSame(['grace'], $this->groupKeys());
    }

    public function test_shop_past_grace_is_locked(): void
    {
        Shop::factory()->package('booth')->create([
            'subscription_ends_at' => now()->subDays(30),
        ]);

        $this->assertSame(['locked'], $this->groupKeys());
    }

    public function test_comped_and_free_shops_never_appear(): void
    {
        // Dostęp gratisowy nie wygasa, a pakiet darmowy nie ma czego wygasić —
        // data w bazie nic tu nie znaczy i nie wolno jej alarmować.
        Shop::factory()->package('pavilion')->create([
            'comped' => true,
            'subscription_ends_at' => now()->subYear(),
        ]);
        Shop::factory()->package('stall')->create(['subscription_ends_at' => now()->addDays(3)]);

        $this->assertSame([], PackageAttention::groups());
    }

    public function test_shop_awaiting_deletion_is_not_chased(): void
    {
        Shop::factory()->package('booth')->create([
            'subscription_ends_at' => now()->addDays(5),
            'deletion_scheduled_at' => now()->addDays(7),
        ]);

        $this->assertSame([], PackageAttention::groups());
    }

    public function test_paid_payment_without_invoice_is_flagged(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addMonths(6)]);
        PackagePayment::factory()->for($shop)->create(['amount' => 750, 'invoice_id' => null]);

        $this->assertSame(['uninvoiced'], $this->groupKeys());

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('Opłacone bez faktury')
            ->assertSee('750,00 zł');
    }

    public function test_payment_with_invoice_is_silent(): void
    {
        $shop = Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addMonths(6)]);
        PackagePayment::factory()->for($shop)->create(['invoice_id' => '551', 'invoice_number' => 'FV 1/2026']);

        $this->assertSame([], PackageAttention::groups());
    }

    public function test_pending_payment_is_flagged_only_after_the_configured_delay(): void
    {
        // Świeży „pending" to normalny stan płatności w toku — alarm od razu po
        // kliknięciu „Kup" zapełniłby listę czymś, co samo się rozwiąże.
        $shop = Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addMonths(6)]);
        $fresh = PackagePayment::factory()->pending()->for($shop)->create();

        $this->assertSame([], PackageAttention::groups());

        $fresh->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->assertSame(['abandoned'], $this->groupKeys());
    }

    public function test_empty_list_says_so_instead_of_showing_zeros(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('Nic nie wymaga uwagi')
            ->assertDontSee('Kończy się wkrótce');
    }

    public function test_groups_are_ordered_by_urgency_and_named_like_the_seller_screen(): void
    {
        // Etykiety trzymają się słownika, który sprzedawca już zna z „Mojego
        // pakietu" („wygasł", „termin minął"). Wewnętrzne „zamek" z komentarzy
        // w kodzie nie ma prawa wyjść na ekran — Rafał sam nie wiedział, co
        // znaczy, a to on jest tu jedynym użytkownikiem.
        $admin = User::factory()->admin()->create();
        Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addDays(10)]);
        Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->subDays(2)]);
        Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->subDays(30)]);

        $this->assertSame(['locked', 'grace', 'expiring'], $this->groupKeys());

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('Wygasł')
            ->assertSee('Po terminie')
            ->assertSee('Kończy się wkrótce')
            ->assertDontSee('zamek');
    }
}
