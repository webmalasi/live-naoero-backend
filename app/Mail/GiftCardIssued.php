<?php

namespace App\Mail;

use App\Models\GiftCardOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A Naoero Airlines gift card, emailed to its recipient (and the buyer). */
class GiftCardIssued extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public GiftCardOrder $card) {}

    public function envelope(): Envelope
    {
        $from = $this->card->recipient_name ? "{$this->card->buyer_name} sent you" : 'Your';

        return new Envelope(subject: "{$from} a Naoero Airlines gift card ({$this->card->formatted_amount})");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.gift-card-issued');
    }
}
