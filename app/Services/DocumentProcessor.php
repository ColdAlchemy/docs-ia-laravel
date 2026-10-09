<?php

namespace App\Services;

use App\Ai\Contracts\AiClient;
use App\Ai\Contracts\Embedder;
use App\Models\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Pipeline de um documento: (1) divide em trechos e indexa os embeddings;
 * (2) pede à IA uma extração estruturada (tipo, resumo, partes, prazos, valores).
 */
class DocumentProcessor
{
    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'tipo' => ['type' => 'string', 'enum' => ['contrato', 'notificacao', 'peticao', 'outro']],
            'resumo' => ['type' => 'string', 'description' => 'Resumo em até 3 frases.'],
            'partes' => ['type' => 'array', 'items' => ['type' => 'string']],
            'prazos' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['descricao' => ['type' => 'string'], 'data' => ['type' => 'string', 'description' => 'dd/mm/aaaa']],
                    'required' => ['descricao', 'data'],
                ],
            ],
            'valores' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['descricao' => ['type' => 'string'], 'valor' => ['type' => 'string']],
                    'required' => ['descricao', 'valor'],
                ],
            ],
        ],
        'required' => ['tipo', 'resumo', 'partes', 'prazos', 'valores'],
    ];

    private const SYSTEM = 'Você extrai dados de documentos em português. Use apenas o que está escrito no texto; '
        .'se algo não constar, devolva lista vazia. Não invente partes, datas nem valores.';

    public function __construct(
        private readonly AiClient $ai,
        private readonly Embedder $embedder,
        private readonly Chunker $chunker,
    ) {}

    public function process(Documento $documento): Documento
    {
        $documento->update(['status' => Documento::STATUS_PROCESSANDO, 'erro' => null]);

        $dados = $this->ai->structured(self::SYSTEM, $documento->conteudo, self::SCHEMA);

        DB::transaction(function () use ($documento, $dados) {
            $documento->trechos()->delete();

            foreach ($this->chunker->split($documento->conteudo) as $ordem => $texto) {
                $documento->trechos()->create([
                    'ordem' => $ordem,
                    'texto' => $texto,
                    'embedding' => $this->embedder->embed($texto),
                ]);
            }

            $documento->update(['dados' => $dados, 'status' => Documento::STATUS_PRONTO]);
        });

        return $documento->refresh();
    }
}
