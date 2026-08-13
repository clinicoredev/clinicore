<?php

namespace App\Notifications;

use App\Models\Ausencia;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EstadoAusenciaModificado extends Notification
{
    use Queueable;

    public $ausencia;

    public function __construct(Ausencia $ausencia)
    {
        $this->ausencia = $ausencia;
    }

    // Indicamos que queremos guardarla en la base de datos
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    // Aquí estructuramos el JSON que consumirá Vue
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => 'Petición ' . ucfirst($this->ausencia->estado),
            'mensaje' => 'Tu solicitud de ' . str_replace('_', ' ', $this->ausencia->tipo) . ' ha sido ' . $this->ausencia->estado . '.',
            'estado' => $this->ausencia->estado, // 'aprobada' o 'denegada'
            'fecha' => now()->format('d/m/Y H:i')
        ];
    }
}