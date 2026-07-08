<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LimitacionGuardia extends Model
{
    protected $table = 'limitaciones_guardia';
    protected $guarded = [];

    public function facultativo()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}