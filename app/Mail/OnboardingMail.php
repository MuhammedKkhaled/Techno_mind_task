<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.onboarding');
    }
}
