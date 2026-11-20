<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEspecialidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Festivo extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use BelongsToEspecialidad, HasFactory;

    protected $fillable = [
        'especialidad_id',
        'fecha',
        'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];
}
