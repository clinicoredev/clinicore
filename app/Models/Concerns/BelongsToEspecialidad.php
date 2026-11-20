<?php

namespace App\Models\Concerns;

use App\Models\Especialidad;
use App\Models\Scopes\EspecialidadScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToEspecialidad
{
    public static function bootBelongsToEspecialidad(): void
    {
        static::addGlobalScope(new EspecialidadScope());

        static::creating(function (Model $model): void {
            if ($model->getAttribute('especialidad_id') === null && Auth::hasUser()) {
                $user = Auth::user();
                if ($user instanceof User && $user->especialidad_id !== null) {
                    $model->setAttribute('especialidad_id', $user->especialidad_id);
                }
            }
        });
    }

    /**
     * Resolve route model binding enforcing 403 Forbidden on cross-tenant access.
     *
     * @param mixed $value
     * @param string|null $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $model = $this->resolveRouteBindingQuery(
            $this->withoutGlobalScope(EspecialidadScope::class),
            $value,
            $field
        )->first();

        if ($model !== null && Auth::hasUser()) {
            $user = Auth::user();
            if ($user instanceof User && $user->especialidad_id !== null && !$user->hasRole('SuperAdmin')) {
                if ((int) $model->getAttribute('especialidad_id') !== (int) $user->especialidad_id) {
                    abort(403);
                }
            }
        }

        return $model;
    }

    /**
     * @return BelongsTo<Especialidad, $this>
     */
    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForEspecialidad(Builder $query, int $especialidadId): Builder
    {
        return $query->withoutGlobalScope(EspecialidadScope::class)
            ->where($this->qualifyColumn('especialidad_id'), $especialidadId);
    }
}
