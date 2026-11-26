<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campañas de cobranza: un envío masivo de una plantilla a un segmento de deudores.
        Schema::create('campanas_cobranza', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('segmento', 30);                   // por_vencer | con_recargo | con_bloqueo | atraso
            $table->string('plantilla', 60);
            $table->json('filtros')->nullable();               // sede, carrera, grupo, días, saldo mínimo
            $table->unsignedInteger('destinatarios')->default(0);
            $table->unsignedInteger('en_cola')->default(0);
            $table->json('omitidos')->nullable();              // motivo => cantidad
            $table->decimal('saldo_objetivo', 12, 2)->default(0); // lo que debían al enviar
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            $table->foreignId('campana_id')->nullable()->after('user_id')->constrained('campanas_cobranza')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensajes', fn (Blueprint $t) => $t->dropConstrainedForeignId('campana_id'));
        Schema::dropIfExists('campanas_cobranza');
    }
};
