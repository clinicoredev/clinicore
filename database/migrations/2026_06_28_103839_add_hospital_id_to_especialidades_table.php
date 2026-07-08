<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('especialidades', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->constrained('hospitales')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::table('especialidades', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropColumn(['hospital_id']);
        });
    }
};