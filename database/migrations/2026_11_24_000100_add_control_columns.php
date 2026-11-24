<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un cajero con pagos registrados no se puede borrar (FK restrict): se desactiva.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('password');
        });

        // Auditoría de cargos capturados a mano (null = generado por el sistema).
        Schema::table('adeudos', function (Blueprint $table) {
            $table->foreignId('creado_por')->nullable()->after('pagado')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('adeudos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creado_por');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
