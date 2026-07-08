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
        Schema::create('limitaciones_guardia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('especialidad_id')->constrained('especialidades')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Tipo: 'dia_semana' (1=Lunes... 7=Domingo) ó 'fecha_concreta' (2026-11-24)
            $table->string('tipo'); 
            $table->string('valor'); // Guarda "1" (Lunes) o "2026-11-24"
            $table->string('motivo')->nullable(); // Ej: "Reducción de jornada por cuidado de hijo"

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('limitacion_guardias');
    }
};
