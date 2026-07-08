<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Guardia extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'guardias';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    public function facultativo()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll() // Que vigile todos los campos
            ->logOnlyDirty() // Que solo guarde si algo realmente ha cambiado
            ->setDescriptionForEvent(fn(string $eventName) => "La guardia ha sido {$eventName}");
    }
}