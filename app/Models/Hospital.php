<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Especialidad> $especialidades
 */
class Hospital extends Model
{
    protected $table = 'hospitales'; // <--- OBLIGATORIO PARA EVITAR EL BUG INGLÉS
    
    protected $guarded = [];

    /**
     * @return HasMany<Especialidad, $this>
     */
    public function especialidades(): HasMany
    {
        return $this->hasMany(Especialidad::class);
    }
}