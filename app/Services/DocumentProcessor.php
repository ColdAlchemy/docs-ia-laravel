<?php

namespace App\Services;

use App\Ai\Contracts\AiClient;
use App\Ai\Contracts\Embedder;
use App\Models\Documento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pipeline de um documento: (1) divide em trechos e indexa os embeddings;
 * (2) pede à IA uma extração estruturada (tipo, resumo, partes, prazos, valores);
 * (3) transforma os prazos extraídos em registros com data, usados no calendário.
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

            foreach ($this->chunker->splitWithOffsets($documento->conteudo) as $ordem => $trecho) {
                $documento->trechos()->create([
                    'ordem' => $ordem,
                    'texto' => $trecho['texto'],
                    'inicio' => $trecho['inicio'],
                    'fim' => $trecho['fim'],
                    'embedding' => $this->embedder->embed($trecho['texto']),
                ]);
            }

            $this->registrarPrazos($documento, $dados['prazos'] ?? []);

            $documento->update(['dados' => $dados, 'status' => Documento::STATUS_PRONTO]);
        });

        return $documento->refresh();
    }

    /** @param array<int, array{descricao?: string, data?: string}> $prazos */
    private function registrarPrazos(Documento $documento, array $prazos): void
    {
        $documento->prazos()->delete();
        if (! $documento->espaco_id) {
            return;
        }

        foreach ($prazos as $prazo) {
            $data = $this->parseData((string) ($prazo['data'] ?? ''));
            if ($data) {
                $documento->prazos()->create([
                    'espaco_id' => $documento->espaco_id,
                    'data' => $data->toDateString(),
                    'descricao' => (string) ($prazo['descricao'] ?? 'Prazo'),
                ]);
            }
        }
    }

    /** Aceita dd/mm/aaaa; devolve null para datas que não existem. */
    private function parseData(string $texto): ?CarbonImmutable
    {
        if (! preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($texto), $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        return CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1]);
    }
}
