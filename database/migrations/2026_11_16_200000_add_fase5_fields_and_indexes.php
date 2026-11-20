<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitudes_permuta')) {
            Schema::table('solicitudes_permuta', function (Blueprint $table) {
                if (!Schema::hasColumn('solicitudes_permuta', 'aceptado_destinatario_at')) {
                    $table->timestamp('aceptado_destinatario_at')->nullable()->after('motivo');
                }
                if (!Schema::hasIndex('solicitudes_permuta', 'idx_solicitudes_permuta_esp_dest_estado')) {
                    $table->index(['especialidad_id', 'destinatario_id', 'estado'], 'idx_solicitudes_permuta_esp_dest_estado');
                }
            });
        }

        if (Schema::hasTable('festivos')) {
            Schema::table('festivos', function (Blueprint $table) {
                if (!Schema::hasIndex('festivos', 'uniq_festivos_especialidad_fecha')) {
                    $table->unique(['especialidad_id', 'fecha'], 'uniq_festivos_especialidad_fecha');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'porcentaje_jornada')) {
                    $table->unsignedTinyInteger('porcentaje_jornada')->default(100)->after('calendar_token');
                }
                if (!Schema::hasColumn('users', 'exento_guardias')) {
                    $table->boolean('exento_guardias')->default(false)->after('porcentaje_jornada');
                }
                if (!Schema::hasColumn('users', 'motivo_exencion')) {
                    $table->string('motivo_exencion', 255)->nullable()->after('exento_guardias');
                }
                if (!Schema::hasColumn('users', 'gcal_access_token')) {
                    $table->text('gcal_access_token')->nullable()->after('gcal_last_synced_at');
                }
                if (!Schema::hasColumn('users', 'gcal_refresh_token')) {
                    $table->text('gcal_refresh_token')->nullable()->after('gcal_access_token');
                }
                if (!Schema::hasColumn('users', 'gcal_token_expires_at')) {
                    $table->timestamp('gcal_token_expires_at')->nullable()->after('gcal_refresh_token');
                }
            });
        }

        if (Schema::hasTable('guardias')) {
            Schema::table('guardias', function (Blueprint $table) {
                if (!Schema::hasColumn('guardias', 'modalidad')) {
                    $table->string('modalidad', 20)->default('presencial')->after('tipo');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitudes_permuta')) {
            Schema::table('solicitudes_permuta', function (Blueprint $table) {
                if (Schema::hasIndex('solicitudes_permuta', 'idx_solicitudes_permuta_esp_dest_estado')) {
                    $table->dropIndex('idx_solicitudes_permuta_esp_dest_estado');
                }
                if (Schema::hasColumn('solicitudes_permuta', 'aceptado_destinatario_at')) {
                    $table->dropColumn('aceptado_destinatario_at');
                }
            });
        }

        if (Schema::hasTable('festivos')) {
            Schema::table('festivos', function (Blueprint $table) {
                if (Schema::hasIndex('festivos', 'uniq_festivos_especialidad_fecha')) {
                    $table->dropUnique('uniq_festivos_especialidad_fecha');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $cols = array_filter(
                    [
                        'porcentaje_jornada',
                        'exento_guardias',
                        'motivo_exencion',
                        'gcal_access_token',
                        'gcal_refresh_token',
                        'gcal_token_expires_at',
                    ],
                    fn(string $col): bool => Schema::hasColumn('users', $col)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('guardias')) {
            Schema::table('guardias', function (Blueprint $table) {
                if (Schema::hasColumn('guardias', 'modalidad')) {
                    $table->dropColumn('modalidad');
                }
            });
        }
    }
};
