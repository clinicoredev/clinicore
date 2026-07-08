<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    protected $table = 'hospitales'; // <--- OBLIGATORIO PARA EVITAR EL BUG INGLÉS
    
    protected $guarded = [];

    public function especialidades() {
        return $this->hasMany(Especialidad::class);
    }
}