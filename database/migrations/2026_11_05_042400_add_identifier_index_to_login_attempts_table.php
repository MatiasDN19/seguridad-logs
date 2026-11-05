<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_attempts', function (Blueprint $table) {
            // Para la detección por cuenta: "fallos contra esta cuenta en los últimos 15 minutos"
            $table->index(['identifier', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('login_attempts', function (Blueprint $table) {
            $table->dropIndex(['identifier', 'status', 'created_at']);
        });
    }
};
