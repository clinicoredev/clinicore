<?php

namespace App\Notifications;

use App\Models\Ausencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EstadoAusenciaModificado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Ausencia $ausencia
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
        $tipoLegible = str_replace('_', ' ', (string) $this->ausencia->tipo);
        $estado = (string) $this->ausencia->estado;

        return (new MailMessage)
            ->subject('CliniCore: Solicitud de ausencia ' . $estado)
            ->greeting('Hola ' . ($notifiable->name ?? 'Facultativo') . ',')
            ->line("Tu solicitud de {$tipoLegible} ha sido {$estado}.")
            ->action('Ver mis ausencias', url('/ausencias'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => 'Petición ' . ucfirst((string) $this->ausencia->estado),
            'mensaje' => 'Tu solicitud de ' . str_replace('_', ' ', (string) $this->ausencia->tipo) . ' ha sido ' . $this->ausencia->estado . '.',
            'estado' => $this->ausencia->estado,
            'ausencia_id' => $this->ausencia->id,
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }
}
