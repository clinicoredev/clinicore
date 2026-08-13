<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class InvitacionSaaS extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $urlInvitacion;

    public function __construct(User $user)
    {
        $this->user = $user;
        // Aquí ocurre la magia: Generamos una URL encriptada y única que caduca en 48 horas
        $this->urlInvitacion = URL::temporarySignedRoute(
            'onboarding.password', now()->addHours(48), ['user' => $user->id]
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Has sido invitado a CliniCore');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitacion');
    }
}