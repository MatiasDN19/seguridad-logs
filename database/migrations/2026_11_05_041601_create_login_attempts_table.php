<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            // Email usado en el intento, tal como pide el enunciado.
            // (En el TP sobre proyecto iniciado se guardaba un hash: ver el informe.)
            $table->string('identifier')->nullable();
            $table->string('ip_address', 45); // 45 caracteres alcanza para IPv6
            $table->string('status', 20);     // success | failed | blocked
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Índice para "fallos de esta IP en los últimos 5 minutos"
            $table->index(['ip_address', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
