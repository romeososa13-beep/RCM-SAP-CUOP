<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bajas y egresados no generan mensualidad, así que pueden no tener plan.
     * La regla "plan obligatorio para ACTIVOS" se valida en la aplicación.
     */
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->foreignId('plan_pago_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->foreignId('plan_pago_id')->nullable(false)->change();
        });
    }
};
