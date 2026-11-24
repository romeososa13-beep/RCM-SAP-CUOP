<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carreras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);              // Ej. EDUCACIÓN NO ESCOLARIZADO
            $table->string('modalidad', 50);            // ESCOLARIZADO / NO ESCOLARIZADO / EN LÍNEA
            $table->timestamps();

            $table->unique(['nombre', 'modalidad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carreras');
    }
};
