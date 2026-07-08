<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('guardias', function (Blueprint $table) {
            // Añadimos una bandera para proteger las guardias puestas a mano
            $table->boolean('is_manual')->default(false)->after('estado');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guardias', function (Blueprint $table) {
            //
        });
    }
};
