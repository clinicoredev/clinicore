<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_token', 60)->unique()->nullable()->after('remember_token');
        });

        // Generamos un token aleatorio para los usuarios que ya tenías creados
        foreach (User::all() as $user) {
            $user->update(['calendar_token' => Str::random(40)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calendar_token');
        });
    }
};