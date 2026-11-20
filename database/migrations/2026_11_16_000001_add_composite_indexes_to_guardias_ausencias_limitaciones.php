<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guardias', function (Blueprint $table) {
            $table->index(['especialidad_id', 'fecha'], 'idx_guardias_especialidad_fecha');
        });

        Schema::table('ausencias', function (Blueprint $table) {
            $table->index(['especialidad_id', 'estado', 'fecha_inicio', 'fecha_fin'], 'idx_ausencias_especialidad_estado_fechas');
        });

        Schema::table('limitaciones_guardia', function (Blueprint $table) {
            $table->index(['especialidad_id', 'user_id'], 'idx_limitaciones_especialidad_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guardias', function (Blueprint $table) {
            $table->dropIndex('idx_guardias_especialidad_fecha');
        });

        Schema::table('ausencias', function (Blueprint $table) {
            $table->dropIndex('idx_ausencias_especialidad_estado_fechas');
        });

        Schema::table('limitaciones_guardia', function (Blueprint $table) {
            $table->dropIndex('idx_limitaciones_especialidad_user');
        });
    }
};
