<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * @implements Scope<Model>
 */
class EspecialidadScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!Auth::hasUser()) {
            return;
        }

        $user = Auth::user();
        if (!$user instanceof User) {
            return;
        }

        if ($user->especialidad_id !== null && !$user->hasRole('SuperAdmin')) {
            $builder->where($model->qualifyColumn('especialidad_id'), $user->especialidad_id);
        }
    }
}
