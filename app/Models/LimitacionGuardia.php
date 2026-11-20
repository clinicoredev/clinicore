<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEspecialidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimitacionGuardia extends Model
{
    use BelongsToEspecialidad;

    protected $table = 'limitaciones_guardia';
    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function facultativo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
