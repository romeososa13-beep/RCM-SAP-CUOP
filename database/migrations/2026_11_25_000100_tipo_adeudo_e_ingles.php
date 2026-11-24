<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El tipo decide si un cargo genera recargo y bloqueo.
        Schema::table('adeudos', function (Blueprint $table) {
            $table->enum('tipo', ['COLEGIATURA', 'INGLES', 'OTRO'])->default('OTRO')->after('concepto');
            $table->index(['tipo', 'pagado', 'fecha_vencimiento']);
        });

        // Cargos existentes: las mensualidades son colegiatura; el resto, OTRO.
        DB::table('adeudos')->where('concepto', 'like', 'MENSUALIDAD %')->update(['tipo' => 'COLEGIATURA']);
        DB::table('adeudos')->where('concepto', 'like', 'INGLÉS %')->update(['tipo' => 'INGLES']);

        // Costo mensual de inglés del alumno (NULL = no cursa inglés).
        Schema::table('alumnos', function (Blueprint $table) {
            $table->decimal('costo_ingles', 10, 2)->nullable()->after('plan_pago_id');
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', fn (Blueprint $table) => $table->dropColumn('costo_ingles'));
        Schema::table('adeudos', function (Blueprint $table) {
            $table->dropIndex(['tipo', 'pagado', 'fecha_vencimiento']);
            $table->dropColumn('tipo');
        });
    }
};
