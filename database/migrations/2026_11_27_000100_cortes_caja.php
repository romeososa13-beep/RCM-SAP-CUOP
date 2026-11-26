<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cierre diario por cajero: foto de los totales; con el corte cerrado no se cobra ni se cancela ese día. */
    public function up(): void
    {
        Schema::create('cortes_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();   // cajero
            $table->date('fecha');
            $table->unsignedInteger('num_pagos');
            $table->decimal('total', 12, 2);
            $table->json('por_forma');
            $table->timestamp('cerrado_at');
            $table->foreignId('cerrado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('reabierto_at')->nullable();
            $table->foreignId('reabierto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_reapertura', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cortes_caja');
    }
};
