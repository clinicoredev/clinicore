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
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();

            // 1. Aislamiento por Servicio (Seguridad Multi-Tenant)
            $table->foreignId('especialidad_id')->constrained('especialidades')->cascadeOnDelete();
            
            // 2. El solicitante (Flecha 1 hacia users)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 3. Datos de la solicitud
            $table->string('tipo'); // 'congreso', 'vacaciones', 'asuntos_propios', 'baja_medica'
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->text('motivo')->nullable(); // Ej: "Ponente principal en Congreso SEO"

            // 4. Máquina de estados laborales
            $table->string('estado')->default('pendiente'); // 'pendiente', 'aprobada', 'denegada'
            
            // 5. El firmante (Flecha 2 hacia users - Es nullable porque al nacer nadie la ha aprobado aún)
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ausencias');
    }
};
