<?php

namespace Tests\Feature\Seller;

use App\Livewire\Seller\OrderMessenger;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Moduł „Napisz do klienta" na szczególe zamówienia: wolna wiadomość od
 * sprzedawcy do kupującego. Mail idzie w szacie sklepu, niesie pozycje
 * zamówienia (sprawa zwykle dotyczy któregoś z produktów) i zachęca do
 * odpowiedzi, która trafia wprost do sprzedawcy. Kopia dla sprzedawcy — tylko
 * na jego wyraźne życzenie.
 */
class OrderMessengerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);

        return [$seller, $shop];
    }

    private function orderWithItem(Shop $shop): Order
    {
        $order = Order::factory()->for($shop)->create([
            'buyer_email' => 'kupujacy@example.com',
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Kowalska',
        ]);

        OrderItem::factory()->for($order)->create([
            'name' => 'Wazon ceramiczny',
            'quantity' => 2,
            'unit_price_gross' => 60.00,
            'line_total_gross' => 120.00,
        ]);

        return $order;
    }

    public function test_seller_sends_a_message_that_carries_the_order_items(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = $this->orderWithItem($shop);

        Livewire::actingAs($seller)
            ->test(OrderMessenger::class, ['order' => $order])
            ->set('body', 'Wazon dojechał w innym odcieniu niż na zdjęciu.')
            ->call('send')
            ->assertOk()
            ->assertSet('body', '')          // pole wyczyszczone pod kolejną wiadomość
            ->assertSet('sent', true);

        $this->assertSame(1, EmailMessage::count());

        $mail = EmailMessage::first();
        $this->assertSame('kupujacy@example.com', $mail->to_email);
        $this->assertSame('Wiadomość w sprawie zamówienia #'.$order->number.' — '.$shop->name, $mail->subject);
        $this->assertSame($shop->name, $mail->from_name);            // tożsamość „od sklepu"
        $this->assertSame($shop->contact_email, $mail->reply_to);    // odpowiedź wraca do sprzedawcy
        $this->assertSame($shop->id, $mail->shop_id);                // branding per sklep

        $body = json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Wazon dojechał w innym odcieniu', $body);

        // Pozycja z kwotą i suma — bez tego klient nie wie, o czym mowa.
        $this->assertStringContainsString('Wazon ceramiczny', $body);
        $this->assertStringContainsString('120,00', $body);

        // Notka o zachowaniu wątku korespondencji.
        $this->assertStringContainsString('Odpowiedz', json_encode($mail->outro_lines, JSON_UNESCAPED_UNICODE));
    }

    public function test_blank_line_starts_a_new_paragraph(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = $this->orderWithItem($shop);

        Livewire::actingAs($seller)
            ->test(OrderMessenger::class, ['order' => $order])
            ->set('body', "Dzień dobry,\nmam pytanie.\n\nProszę o odpowiedź.")
            ->call('send');

        $lines = EmailMessage::first()->intro_lines;

        // Akapit pierwszy trzyma obie linie razem, pusta linia otwiera drugi.
        $this->assertSame(['Dzień dobry,', 'mam pytanie.'], $lines[0]);
        $this->assertSame(['Proszę o odpowiedź.'], $lines[1]);
    }

    public function test_copy_goes_to_the_seller_only_when_asked(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = $this->orderWithItem($shop);

        Livewire::actingAs($seller)
            ->test(OrderMessenger::class, ['order' => $order])
            ->set('body', 'Zamówienie jest gotowe do odbioru.')
            ->call('send');

        // Domyślnie kopii nie ma — tylko mail do klienta.
        $this->assertSame(1, EmailMessage::count());

        Livewire::actingAs($seller)
            ->test(OrderMessenger::class, ['order' => $order])
            ->set('body', 'Zamówienie jest gotowe do odbioru.')
            ->set('copyToMe', true)
            ->call('send');

        $this->assertSame(3, EmailMessage::count());

        $copy = EmailMessage::where('to_email', $seller->email)->sole();
        $this->assertSame('Kopia: wiadomość do klienta — zamówienie #'.$order->number, $copy->subject);

        $body = json_encode($copy->intro_lines, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('To kopia wiadomości wysłanej do', $body);   // nie odpowiedź klienta
        $this->assertStringContainsString('Zamówienie jest gotowe do odbioru.', $body);
        $this->assertStringContainsString('Wazon ceramiczny', $body);                  // kopia = to, co dostał klient
    }

    public function test_empty_message_is_rejected_and_sends_nothing(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $order = $this->orderWithItem($shop);

        Livewire::actingAs($seller)
            ->test(OrderMessenger::class, ['order' => $order])
            ->set('body', '   ')
            ->call('send')
            ->assertHasErrors(['body' => 'required'])
            ->assertSet('sent', false);

        $this->assertSame(0, EmailMessage::count());
    }

    public function test_seller_cannot_write_to_a_buyer_of_another_shop(): void
    {
        [, $shop] = $this->sellerWithShop();
        $order = $this->orderWithItem($shop);

        [$intruder] = $this->sellerWithShop();

        Livewire::actingAs($intruder)
            ->test(OrderMessenger::class, ['order' => $order])
            ->set('body', 'Wiadomość z obcego sklepu.')
            ->call('send')
            ->assertForbidden();

        $this->assertSame(0, EmailMessage::count());
    }
}
