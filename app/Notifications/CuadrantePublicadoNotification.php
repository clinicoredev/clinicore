<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CuadrantePublicadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $especialidadId,
        public readonly string $colectivo,
        public readonly int $mes,
        public readonly int $anio,
        public readonly int $turnosAsignados = 0
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
        $periodo = sprintf('%02d/%d', $this->mes, $this->anio);
        $etiqueta = $this->colectivo === 'residentes' ? 'Residentes' : 'Adjuntos';

        return (new MailMessage)
            ->subject("CliniCore: Cuadrante de Guardias Publicado ({$periodo})")
            ->greeting('Hola ' . ($notifiable->name ?? 'Facultativo') . ',')
            ->line("Se ha publicado el cuadrante de guardias ({$etiqueta}) correspondiente al periodo {$periodo}.")
            ->line("Tienes {$this->turnosAsignados} turno(s) asignado(s) en este mes.")
            ->action('Consultar Cuadrante', url($this->colectivo === 'residentes' ? '/residentes/guardias' : '/guardias'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $periodo = sprintf('%02d/%d', $this->mes, $this->anio);
        $etiqueta = $this->colectivo === 'residentes' ? 'Residentes' : 'Adjuntos';

        return [
            'titulo' => "Cuadrante Publicado ({$periodo})",
            'mensaje' => "Nuevo cuadrante de {$etiqueta} publicado para {$periodo} ({$this->turnosAsignados} turnos asignados).",
            'estado' => 'publicado',
            'especialidad_id' => $this->especialidadId,
            'colectivo' => $this->colectivo,
            'mes' => $this->mes,
            'anio' => $this->anio,
            'turnos_asignados' => $this->turnosAsignados,
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }
}
