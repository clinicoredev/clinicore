<?php

namespace App\Notifications;

use App\Models\SolicitudPermuta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PermutaResueltaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SolicitudPermuta $solicitud
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $estado = (string) $this->solicitud->estado;

        return (new MailMessage)
            ->subject('CliniCore: Solicitud de permuta ' . $estado)
            ->greeting('Hola ' . ($notifiable->name ?? 'Facultativo') . ',')
            ->line("La solicitud de intercambio de guardia ha sido {$estado}.")
            ->action('Ver Permutas', url('/permutas'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => 'Permuta ' . ucfirst((string) $this->solicitud->estado),
            'mensaje' => 'Tu solicitud de intercambio de guardia ha sido ' . $this->solicitud->estado . '.',
            'estado' => $this->solicitud->estado,
            'solicitud_permuta_id' => $this->solicitud->id,
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }
}
