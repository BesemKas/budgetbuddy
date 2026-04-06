<?php

namespace App\Mail;

use App\Models\BudgetInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BudgetInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BudgetInvitation $invitation,
        public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You are invited to join :budget on Budget Buddy', [
                'budget' => $this->invitation->budget->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.budget-invitation-text',
        );
    }
}
