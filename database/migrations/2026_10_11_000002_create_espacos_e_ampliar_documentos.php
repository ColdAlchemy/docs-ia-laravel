<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Espaço de trabalho: o "cartório" de uma equipe. Todo dado pertence a um espaço.
        Schema::create('espacos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->foreignId('dono_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('espaco_user', function (Blueprint $table) {
            $table->foreignId('espaco_id')->constrained('espacos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['espaco_id', 'user_id']);
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('espaco_id')->nullable()->constrained('espacos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Número de registro sequencial dentro do espaço (exibido como 0007/2026).
            $table->unsignedInteger('numero')->default(0);
            $table->string('origem')->default('texto'); // texto | arquivo | pdf
            $table->string('arquivo_nome')->nullable();
        });

        Schema::table('trechos', function (Blueprint $table) {
            // Posição do trecho no texto original (em caracteres), para o leitor.
            $table->unsignedInteger('inicio')->nullable();
            $table->unsignedInteger('fim')->nullable();
        });

        Schema::create('prazos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espaco_id')->constrained('espacos')->cascadeOnDelete();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->date('data')->index();
            $table->string('descricao');
            $table->timestamps();
        });

        Schema::create('conversas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espaco_id')->constrained('espacos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Preenchido quando a conversa é sobre um único documento (leitor).
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->cascadeOnDelete();
            $table->string('titulo');
            $table->timestamps();
        });

        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversa_id')->constrained('conversas')->cascadeOnDelete();
            $table->string('papel'); // user | assistant
            $table->text('conteudo');
            $table->json('fontes')->nullable();
            $table->json('passos')->nullable();
            $table->json('avaliacao')->nullable();
            $table->boolean('fundamentada')->nullable()->index();
            $table->timestamps();
        });

        // Conjunto de perguntas de verificação, reexecutado em lote no painel de qualidade.
        Schema::create('perguntas_verificacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('espaco_id')->constrained('espacos')->cascadeOnDelete();
            $table->string('pergunta', 300);
            $table->boolean('ultima_fundamentada')->nullable();
            $table->text('ultima_resposta')->nullable();
            $table->string('ultimo_motivo')->nullable();
            $table->timestamp('executada_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perguntas_verificacao');
        Schema::dropIfExists('mensagens');
        Schema::dropIfExists('conversas');
        Schema::dropIfExists('prazos');
        Schema::table('trechos', fn (Blueprint $t) => $t->dropColumn(['inicio', 'fim']));
        Schema::table('documentos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('espaco_id');
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['numero', 'origem', 'arquivo_nome']);
        });
        Schema::dropIfExists('espaco_user');
        Schema::dropIfExists('espacos');
    }
};
