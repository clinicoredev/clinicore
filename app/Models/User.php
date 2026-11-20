<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $especialidad_id
 * @property string|null $calendar_token
 * @property int $porcentaje_jornada
 * @property bool $exento_guardias
 * @property string|null $motivo_exencion
 * @property bool $gcal_sync_enabled
 * @property string|null $gcal_calendar_id
 * @property Carbon|null $gcal_last_synced_at
 * @property string|null $gcal_access_token
 * @property string|null $gcal_refresh_token
 * @property Carbon|null $gcal_token_expires_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Especialidad|null $especialidad
 */
#[Fillable([
    'name',
    'email',
    'password',
    'especialidad_id',
    'calendar_token',
    'porcentaje_jornada',
    'exento_guardias',
    'motivo_exencion',
    'gcal_sync_enabled',
    'gcal_calendar_id',
    'gcal_last_synced_at',
    'gcal_access_token',
    'gcal_refresh_token',
    'gcal_token_expires_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'gcal_access_token', 'gcal_refresh_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable, HasRoles, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'porcentaje_jornada' => 'integer',
            'exento_guardias' => 'boolean',
            'gcal_sync_enabled' => 'boolean',
            'gcal_last_synced_at' => 'datetime',
            'gcal_token_expires_at' => 'datetime',
            'gcal_access_token' => 'encrypted',
            'gcal_refresh_token' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Especialidad, $this>
     */
    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class);
    }

    /**
     * @return HasMany<Ausencia, $this>
     */
    public function ausencias(): HasMany
    {
        return $this->hasMany(Ausencia::class);
    }

    /**
     * @return HasMany<Guardia, $this>
     */
    public function guardias(): HasMany
    {
        return $this->hasMany(Guardia::class);
    }
}
