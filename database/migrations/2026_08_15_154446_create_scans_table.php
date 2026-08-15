<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained()->cascadeOnDelete();
            // O cabeçalho é opcional e não tem limite prático — é guardado
            // truncado em vez de rejeitar a leitura.
            $table->string('user_agent', 512)->nullable();
            // Uma leitura nunca é alterada: só existe created_at.
            $table->timestamp('created_at')->nullable();

            // A contagem por dia consulta as leituras de um QR num intervalo.
            $table->index(['qr_code_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
