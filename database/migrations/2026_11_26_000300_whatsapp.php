<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->string('telefono', 20)->index();                // 10 dígitos (clave de conversación)
            $table->string('matricula', 20)->nullable()->index();
            $table->foreign('matricula')->references('matricula')->on('alumnos')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('direccion', ['saliente', 'entrante']);
            $table->enum('tipo', ['plantilla', 'texto', 'bot']);
            $table->string('plantilla', 60)->nullable();
            $table->text('cuerpo');
            $table->json('variables')->nullable();
            // en_cola → enviado → entregado → leido | fallido; entrante: recibido
            $table->string('estado', 20)->default('en_cola')->index();
            $table->string('wa_id', 120)->nullable()->unique();     // id del mensaje en WhatsApp
            $table->text('error')->nullable();
            $table->boolean('requiere_atencion')->default(false)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['telefono', 'created_at']);
            $table->index(['matricula', 'plantilla', 'created_at']);
        });

        // Consentimiento: el alumno puede pedir no recibir mensajes (respondiendo BAJA).
        Schema::table('alumnos', function (Blueprint $table) {
            $table->timestamp('whatsapp_baja_at')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', fn (Blueprint $t) => $t->dropColumn('whatsapp_baja_at'));
        Schema::dropIfExists('whatsapp_mensajes');
    }
};
