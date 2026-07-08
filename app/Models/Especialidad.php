<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    use HasFactory;

    // 1. Decirle al gringo cómo se hace el plural en castellano:
    protected $table = 'especialidades';

    // 2. Permitir que el Seeder rellene las columnas automáticamente:
    protected $guarded = [];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hospital() { return $this->belongsTo(Hospital::class); }
}