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
        Schema::create('guardias', function (Blueprint $table) {
            $table->id();

            // 1. Aislamiento Multi-Tenant
            $table->foreignId('especialidad_id')->constrained('especialidades')->cascadeOnDelete();
            
            // 2. El médico que pringa (pude ser nullable si creamos el hueco vacio para que alguien se apunte)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 3. Datos temporales
            $table->date('fecha')->index(); // Indexado porque filtraremos por mes vista
            $table->string('tipo'); // 'presencial_24h', 'localizada_12h', 'refuerzo_urgencias'
            $table->text('observaciones')->nullable();

            // 4. Estado operativo
            $table->string('estado')->default('programada'); // 'programada', 'realizada', 'cancelada'

            $table->timestamps();

            // ESCUDO DE BASE DE DATOS: Un mismo médico no puede tener dos guardias el mismo día
            $table->unique(['user_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardias');
    }
};
