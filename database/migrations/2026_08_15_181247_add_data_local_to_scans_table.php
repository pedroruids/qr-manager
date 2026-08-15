<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            // O dia a que a leitura pertence no fuso do utilizador, decidido no
            // momento em que se grava. O `created_at` continua em UTC.
            //
            // Sem esta coluna, contar leituras por dia obrigava a converter o
            // fuso dentro do SQL — que cada base de dados escreve à sua maneira
            // e que nenhuma faz bem nos dias de 23 e de 25 horas.
            $table->date('data_local')->nullable()->after('user_agent');

            $table->index(['qr_code_id', 'data_local']);
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropIndex(['qr_code_id', 'data_local']);
            $table->dropColumn('data_local');
        });
    }
};
