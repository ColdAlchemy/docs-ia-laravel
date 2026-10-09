<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->longText('conteudo');
            // pendente -> processando -> pronto | erro
            $table->string('status')->default('pendente')->index();
            $table->text('erro')->nullable();
            // Saída estruturada da IA: tipo, resumo, partes, prazos, valores.
            $table->json('dados')->nullable();
            $table->timestamps();
        });

        Schema::create('trechos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->text('texto');
            $table->json('embedding');
            $table->timestamps();

            $table->index(['documento_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trechos');
        Schema::dropIfExists('documentos');
    }
};
