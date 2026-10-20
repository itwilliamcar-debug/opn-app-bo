<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuthOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otpCode,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OPN — Code d\'authentification',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Bonjour '.e($this->user->name).',</p>'
                .'<p>Votre code de connexion OPN est :</p>'
                .'<p style="font-size:28px;font-weight:bold;letter-spacing:6px;">'.e($this->otpCode).'</p>'
                .'<p>Ce code expire dans 10 minutes. Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.</p>'
                .'<p>— Office Pharmaceutique National</p>',
        );
    }
}
