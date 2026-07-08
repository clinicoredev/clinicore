<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Ausencia extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'ausencias';
    protected $guarded = [];

    // Casts para que Laravel entienda que son fechas reales y no simples Strings
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    // Relación al departamento
    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    // Flecha 1: ¿Quién pidió el día?
    public function solicitante()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Flecha 2: ¿Qué jefe puso la firma?
    public function revisor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "La ausencia ha sido {$eventName}");
    }
}