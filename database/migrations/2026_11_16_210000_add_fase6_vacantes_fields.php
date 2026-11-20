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
                if (!Schema::hasColumn('solicitudes_permuta', 'tipo_solicitud')) {
                    $table->string('tipo_solicitud', 30)->default('permuta')->after('especialidad_id');
                }
                $table->unsignedBigInteger('guardia_destino_id')->nullable()->change();
                $table->unsignedBigInteger('destinatario_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitudes_permuta')) {
            Schema::table('solicitudes_permuta', function (Blueprint $table) {
                if (Schema::hasColumn('solicitudes_permuta', 'tipo_solicitud')) {
                    $table->dropColumn('tipo_solicitud');
                }
            });
        }
    }
};
