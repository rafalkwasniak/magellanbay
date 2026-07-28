<?php

namespace Tests\Feature\Ai;

use App\Exceptions\AiQuotaExceededException;
use App\Models\Shop;
use App\Services\AiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tygodniowy limit zadań AI. Jednostką jest ZADANIE, nie wywołanie: długi tekst
 * poprawiany w kilkunastu fragmentach to dla sprzedawcy jedno kliknięcie i tak
 * ma się policzyć (ustalenie Rafała 2026-07-28).
 *
 * Limit chroni przed pętlą i skryptem, nie przed sprzedawcą — pojedyncze
 * wywołanie kosztuje ułamek grosza, ale throttle 30/min pozwala na ~43 tys.
 * wywołań dziennie z jednego konta.
 */
class AiQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function shop(int $limit = 3): Shop
    {
        $shop = Shop::factory()->create();
        $shop->forceFill(['entitlements' => array_merge($shop->entitlements ?? [], [
            'ai_weekly_limit' => $limit,
        ])])->save();

        return $shop->fresh();
    }

    public function test_packages_carry_their_weekly_limits(): void
    {
        foreach (['stall' => 100, 'booth' => 400, 'pavilion' => 800] as $package => $expected) {
            $shop = Shop::factory()->package($package)->create();

            $this->assertSame($expected, AiQuota::limitFor($shop), 'pakiet '.$package);
        }
    }

    public function test_each_task_takes_one_unit(): void
    {
        $shop = $this->shop(3);
        $quota = app(AiQuota::class);

        $quota->consume($shop);
        $quota->consume($shop);

        $this->assertSame(2, $quota->used($shop));
        $this->assertSame(1, $quota->remaining($shop));
    }

    public function test_exhausted_limit_stops_further_tasks(): void
    {
        $shop = $this->shop(2);
        $quota = app(AiQuota::class);

        $quota->consume($shop);
        $quota->consume($shop);

        try {
            $quota->consume($shop);
            $this->fail('Trzecie zadanie powinno zostać odrzucone.');
        } catch (AiQuotaExceededException $e) {
            $this->assertSame(2, $e->limit);
            // Sprzedawca musi wiedzieć, KIEDY znów będzie mógł kliknąć.
            $this->assertTrue($e->resetsAt->isFuture());
            $this->assertTrue($e->resetsAt->isMonday());
        }
    }

    public function test_fragments_of_one_task_count_once(): void
    {
        $shop = $this->shop(3);
        $quota = app(AiQuota::class);

        // Jedno kliknięcie „Popraw przez AI" na długim opisie = kilkanaście
        // żądań HTTP ze wspólnym identyfikatorem zadania.
        foreach (range(1, 12) as $fragment) {
            $quota->consume($shop, 'zadanie-abc');
        }

        $this->assertSame(1, $quota->used($shop));
    }

    public function test_separate_clicks_count_separately(): void
    {
        $shop = $this->shop(5);
        $quota = app(AiQuota::class);

        $quota->consume($shop, 'zadanie-abc');
        $quota->consume($shop, 'zadanie-xyz');

        $this->assertSame(2, $quota->used($shop));
    }

    public function test_task_id_of_another_shop_does_not_leak(): void
    {
        $first = $this->shop(3);
        $second = $this->shop(3);
        $quota = app(AiQuota::class);

        $quota->consume($first, 'ten-sam-identyfikator');
        $quota->consume($second, 'ten-sam-identyfikator');

        // Znacznik jest per sklep — inaczej cudze zadanie „opłaciłoby" nasze.
        $this->assertSame(1, $quota->used($first));
        $this->assertSame(1, $quota->used($second));
    }

    public function test_limit_resets_with_the_new_week(): void
    {
        $shop = $this->shop(2);
        $quota = app(AiQuota::class);

        Carbon::setTestNow(Carbon::parse('2026-07-28 10:00'));   // wtorek, tydzień 31
        $quota->consume($shop);
        $quota->consume($shop);
        $this->assertSame(0, $quota->remaining($shop));

        Carbon::setTestNow(Carbon::parse('2026-08-03 08:00'));   // poniedziałek, tydzień 32
        $this->assertSame(2, $quota->remaining($shop));
        $this->assertSame(0, $quota->used($shop));

        Carbon::setTestNow();
    }

    public function test_period_key_uses_iso_weeks(): void
    {
        // Sylwester 2026 wypada w czwartek, więc wg ISO należy do tygodnia 53
        // ROKU 2026 — zwykły numer tygodnia potrafi tu dać „1" albo „0".
        $this->assertSame('2026-W53', AiQuota::currentPeriod(Carbon::parse('2026-12-31')));
        $this->assertSame('2026-W31', AiQuota::currentPeriod(Carbon::parse('2026-07-28')));
    }

    public function test_admin_can_raise_the_limit_for_one_shop(): void
    {
        // Uprawnienie żyje w snapshocie sklepu, więc konsola admina podbija je
        // jak każde inne (np. dla dobrego klienta).
        $shop = $this->shop(1000);

        $this->assertSame(1000, AiQuota::limitFor($shop));
    }
}
