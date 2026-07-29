<?php

namespace Tests\Feature\Mail;

use App\Enums\ConsentChannel;
use App\Models\BulkMailing;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Shop;
use App\Services\BulkMailService;
use App\Services\OrderMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Wypis z korespondencji seryjnej: link w stopce KAŻDEGO mailingu, działający
 * bez logowania i natychmiast (RODO art. 7 ust. 3 — zgodę trzeba dać się
 * odwołać równie łatwo, jak została udzielona). Maile transakcyjne stopki NIE
 * dostają — z potwierdzenia zamówienia nie da się wypisać.
 */
class BulkMailUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithBulkMail(): Shop
    {
        return Shop::factory()->withBulkMail()->create();
    }

    private function consentingCustomer(Shop $shop): Customer
    {
        $customer = Customer::factory()->create(['shop_id' => $shop->id]);
        $customer->setConsent(ConsentChannel::Email, true, '127.0.0.1');

        return $customer->fresh();
    }

    private function sendMailingTo(Shop $shop): EmailMessage
    {
        $mailing = $shop->bulkMailings()->create([
            'subject' => 'Nowości w sklepie',
            'body' => 'Zajrzyj do nas.',
        ]);

        EmailMessage::query()->delete();
        app(BulkMailService::class)->send($mailing);

        return EmailMessage::firstOrFail();
    }

    public function test_every_mailing_carries_an_unsubscribe_link(): void
    {
        $shop = $this->shopWithBulkMail();
        $customer = $this->consentingCustomer($shop);

        $message = $this->sendMailingTo($shop);

        $this->assertNotNull($message->unsubscribe_url);
        $this->assertStringContainsString('/wypisz-sie/'.$customer->id, $message->unsubscribe_url);
        $this->assertStringContainsString('signature=', $message->unsubscribe_url);
    }

    public function test_transactional_mail_has_no_unsubscribe_footer(): void
    {
        $shop = $this->shopWithBulkMail();
        $order = Order::factory()->create(['shop_id' => $shop->id]);
        $order->items()->create([
            'product_id' => null, 'name' => 'Doniczka', 'unit_price_gross' => 20,
            'vat_rate' => '23', 'quantity' => 1, 'line_total_gross' => 20,
        ]);

        EmailMessage::query()->delete();
        app(OrderMailer::class)->confirmToCustomer($order->fresh());

        // Z potwierdzenia zamówienia nie wolno się „wypisać" — to mail
        // niezbędny do wykonania umowy.
        $this->assertNull(EmailMessage::firstOrFail()->unsubscribe_url);
    }

    public function test_clicking_the_link_unsubscribes_immediately_without_login(): void
    {
        $shop = $this->shopWithBulkMail();
        $customer = $this->consentingCustomer($shop);
        $url = $this->sendMailingTo($shop)->unsubscribe_url;

        $this->assertTrue($customer->fresh()->hasConsent(ConsentChannel::Email));

        $this->get($url)
            ->assertOk()
            ->assertSee('Wypisaliśmy Cię z wiadomości')
            ->assertSee('Przywróć wiadomości');

        $this->assertFalse($customer->fresh()->hasConsent(ConsentChannel::Email));
    }

    public function test_unsubscribed_customer_is_skipped_by_the_next_mailing(): void
    {
        $shop = $this->shopWithBulkMail();
        $customer = $this->consentingCustomer($shop);
        $this->get($this->sendMailingTo($shop)->unsubscribe_url)->assertOk();

        $customer->refresh();
        $this->assertSame(0, app(BulkMailService::class)->recipientsCount($shop->fresh()));
    }

    public function test_link_without_a_valid_signature_is_rejected(): void
    {
        $shop = $this->shopWithBulkMail();
        $customer = $this->consentingCustomer($shop);

        $this->get('http://'.$shop->slug.'.'.config('tenancy.central_domain').'/wypisz-sie/'.$customer->id)
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->hasConsent(ConsentChannel::Email));
    }

    public function test_a_link_cannot_unsubscribe_a_customer_of_another_shop(): void
    {
        $shop = $this->shopWithBulkMail();
        $other = $this->shopWithBulkMail();
        $customer = $this->consentingCustomer($shop);

        // Podpis wystawiony na cudzą subdomenę — model istnieje, ale nie należy
        // do tego sklepu. Zgody są per sklep, więc to musi odpaść.
        $url = URL::signedRoute('storefront.unsubscribe', [
            'shop' => $other->slug,
            'customer' => $customer->id,
        ]);

        $this->get($url)->assertNotFound();
        $this->assertTrue($customer->fresh()->hasConsent(ConsentChannel::Email));
    }

    public function test_accidental_click_can_be_undone(): void
    {
        $shop = $this->shopWithBulkMail();
        $customer = $this->consentingCustomer($shop);

        $response = $this->get($this->sendMailingTo($shop)->unsubscribe_url)->assertOk();
        $this->assertFalse($customer->fresh()->hasConsent(ConsentChannel::Email));

        $restoreUrl = $response->viewData('restoreUrl');

        $this->post($restoreUrl)
            ->assertOk()
            ->assertSee('Zgoda przywrócona');

        $this->assertTrue($customer->fresh()->hasConsent(ConsentChannel::Email));
    }
}
