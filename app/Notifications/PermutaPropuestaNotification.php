<?php

namespace App\Notifications;

use App\Models\SolicitudPermuta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PermutaPropuestaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $nombreSolicitante;

    public function __construct(
        public readonly SolicitudPermuta $solicitud,
        public readonly string $etapa = 'companero'
    ) {
        $this->nombreSolicitante = $solicitud->solicitante?->name ?? 'Un compañero';
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $solicitante = $this->nombreSolicitante;
        $asunto = $this->etapa === 'aprobacion'
            ? 'CliniCore: Permuta aceptada pendiente de firma de Jefatura/Tutoría'
            : 'CliniCore: Nueva propuesta de intercambio de guardia';

        $linea = $this->etapa === 'aprobacion'
            ? "Una solicitud de permuta de {$solicitante} ha sido aceptada por el compañero y requiere tu firma."
            : "{$solicitante} te ha propuesto un intercambio de turno de guardia.";

        return (new MailMessage)
            ->subject($asunto)
            ->greeting('Hola ' . ($notifiable->name ?? 'Facultativo') . ',')
            ->line($linea)
            ->action('Revisar Solicitud', url('/permutas'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $solicitante = $this->nombreSolicitante;

        return [
            'titulo' => $this->etapa === 'aprobacion' ? 'Permuta Pendiente de Firma' : 'Propuesta de Permuta',
            'mensaje' => $this->etapa === 'aprobacion'
                ? "Permuta de {$solicitante} aceptada por el compañero, lista para firma."
                : "{$solicitante} propone intercambiar un turno de guardia contigo.",
            'estado' => $this->solicitud->estado,
            'solicitud_permuta_id' => $this->solicitud->id,
            'etapa' => $this->etapa,
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }
}
