<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Os últimos caracteres do token em claro. É o que permite a quem
            // tem três tokens saber qual é qual — o hash não serve para isso, e
            // o token em claro não volta a existir depois de ser mostrado.
            $table->string('ultimos_caracteres', 4)->nullable()->after('name');

            // Revogar não apaga: a linha fica, para que o utilizador veja que
            // aquele token existiu e deixou de servir. Quem revoga por engano
            // percebe pelo ecrã o que aconteceu, em vez de ver uma linha
            // desaparecer sem explicação.
            $table->timestamp('revogado_em')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['ultimos_caracteres', 'revogado_em']);
        });
    }
};
