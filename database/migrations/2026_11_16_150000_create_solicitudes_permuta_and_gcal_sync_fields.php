<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solicitudes_permuta')) {
            Schema::create('solicitudes_permuta', function (Blueprint $table) {
                $table->id();
                $table->foreignId('especialidad_id')->constrained('especialidades')->cascadeOnDelete();
                $table->foreignId('guardia_origen_id')->constrained('guardias')->cascadeOnDelete();
                $table->foreignId('guardia_destino_id')->constrained('guardias')->cascadeOnDelete();
                $table->foreignId('solicitante_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('destinatario_id')->constrained('users')->cascadeOnDelete();
                $table->string('colectivo', 30)->default('adjuntos');
                $table->string('estado', 30)->default('pendiente');
                $table->string('motivo', 500)->nullable();
                $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resuelto_at')->nullable();
                $table->timestamps();

                $table->index(['especialidad_id', 'estado'], 'idx_solicitudes_permuta_esp_estado');
                $table->index(['especialidad_id', 'solicitante_id'], 'idx_solicitudes_permuta_esp_solicitante');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'gcal_sync_enabled')) {
                $table->boolean('gcal_sync_enabled')->default(false)->after('calendar_token');
            }
            if (!Schema::hasColumn('users', 'gcal_calendar_id')) {
                $table->string('gcal_calendar_id')->nullable()->after('gcal_sync_enabled');
            }
            if (!Schema::hasColumn('users', 'gcal_last_synced_at')) {
                $table->timestamp('gcal_last_synced_at')->nullable()->after('gcal_calendar_id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_permuta');

        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(
                ['gcal_sync_enabled', 'gcal_calendar_id', 'gcal_last_synced_at'],
                fn(string $col): bool => Schema::hasColumn('users', $col)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
