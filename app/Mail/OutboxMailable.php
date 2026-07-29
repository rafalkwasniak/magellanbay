<?php

namespace App\Mail;

use App\Models\EmailMessage;
use App\Support\MailBranding;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Renderuje zakolejkowany {@see EmailMessage} w naszym brandowalnym komponencie
 * (x-mail.message). Szatę kolorystyczną dobiera MailBranding po `shop_id` —
 * domyślnie system, docelowo per sklep.
 */
class OutboxMailable extends Mailable
{
    public function __construct(
        public readonly EmailMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        // From-address zawsze nasz (SPF/DKIM na kramio.pl), a display-name to nazwa
        // sklepu — mail „od sklepu", nie od platformy. Reply-To kieruje odpowiedź
        // klienta do sprzedawcy. Puste pola = mail platformy → domyślne Kramio.
        $fromName = $this->message->from_name ?: config('mail.from.name');

        return new Envelope(
            from: new Address(config('mail.from.address'), $fromName),
            replyTo: filled($this->message->reply_to) ? [new Address($this->message->reply_to)] : [],
            subject: $this->message->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.outbox',
            with: [
                'brand' => MailBranding::for($this->message->shop_id),
                'preheader' => $this->message->preheader,
                'heading' => $this->message->heading,
                'greeting' => $this->message->greeting,
                'lines' => $this->message->intro_lines ?? [],
                // Treść z edytora sprzedawcy (już zsanityzowana na zapisie).
                // Puste dla maili systemowych — te idą blokami `lines`.
                'bodyHtml' => $this->message->body_html,
                // Migawka promowanego produktu (korespondencja seryjna).
                'productCard' => $this->message->product_card,
                'actionText' => $this->message->action_text,
                'actionUrl' => $this->message->action_url,
                'outroLines' => $this->message->outro_lines ?? [],
                // Puste dla maili transakcyjnych — stopka wypisu pojawia się
                // wyłącznie w korespondencji seryjnej.
                'unsubscribeUrl' => $this->message->unsubscribe_url,
            ],
        );
    }
}
