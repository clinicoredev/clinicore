<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('especialidad_id')->constrained('especialidades')->onDelete('cascade');
            $table->date('fecha');
            $table->string('descripcion')->nullable(); // Ej: "Día de Reyes", "Festivo Local"
            $table->timestamps();

            // Esto es clave: evita que el administrador guarde el mismo festivo dos veces por error
            $table->unique(['especialidad_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festivos');
    }
};