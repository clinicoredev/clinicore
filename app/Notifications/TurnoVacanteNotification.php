<?php

namespace App\Notifications;

use App\Models\SolicitudPermuta;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TurnoVacanteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $nombreSolicitante;
    public readonly string $fechaTurnoFormateada;

    public function __construct(
        public readonly SolicitudPermuta $solicitud
    ) {
        $this->nombreSolicitante = $solicitud->solicitante?->name ?? 'Un compañero del servicio';
        $this->fechaTurnoFormateada = $solicitud->guardiaOrigen?->fecha
            ? Carbon::parse($solicitud->guardiaOrigen->fecha)->format('d/m/Y')
            : 'Próxima fecha';
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
        return (new MailMessage)
            ->subject("CliniCore: Turno vacante disponible en bolsa ({$this->fechaTurnoFormateada})")
            ->greeting('Hola ' . ($notifiable->name ?? 'Facultativo') . ',')
            ->line("Se ha publicado un turno vacante en la bolsa de cobertura para el día {$this->fechaTurnoFormateada} ({$this->nombreSolicitante}).")
            ->line('Si estás disponible y cumples los descansos de saliente, puedes ofrecerte como voluntario desde la bandeja de Permutas y Coberturas.')
            ->action('Ver Bolsa de Vacantes', url('/permutas'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => 'Turno Vacante en Bolsa',
            'mensaje' => "Turno del {$this->fechaTurnoFormateada} ({$this->nombreSolicitante}) disponible para cobertura voluntaria.",
            'estado' => $this->solicitud->estado,
            'solicitud_permuta_id' => $this->solicitud->id,
            'tipo_solicitud' => 'cobertura_vacante',
            'fecha_turno' => $this->fechaTurnoFormateada,
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }
}
