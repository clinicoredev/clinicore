<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEspecialidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SolicitudPermuta extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use BelongsToEspecialidad, HasFactory, LogsActivity;

    protected $table = 'solicitudes_permuta';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'aceptado_destinatario_at' => 'datetime',
            'resuelto_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Guardia, $this>
     */
    public function guardiaOrigen(): BelongsTo
    {
        return $this->belongsTo(Guardia::class, 'guardia_origen_id');
    }

    /**
     * @return BelongsTo<Guardia, $this>
     */
    public function guardiaDestino(): BelongsTo
    {
        return $this->belongsTo(Guardia::class, 'guardia_destino_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "La solicitud de permuta ha sido {$eventName}");
    }
}
