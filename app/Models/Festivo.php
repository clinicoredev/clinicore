<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Festivo extends Model
{
    use HasFactory;

    protected $fillable = [
        'especialidad_id',
        'fecha',
        'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date', // Asegura que Laravel lo trate como un objeto de fecha
    ];

    // Relación con el Tenant (Especialidad)
    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }
}