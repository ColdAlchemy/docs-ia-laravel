<?php

namespace App\Services;

use App\Models\Documento;
use Carbon\Carbon;

/**
 * Ferramentas que o agente pode chamar (tool calling). Cada ferramenta tem:
 * a definição enviada ao LLM (nome, descrição, JSON Schema) e a execução em PHP.
 */
class AgentTools
{
    public function __construct(private readonly VectorSearch $search) {}

    /** @return array<int, array<string, mixed>> */
    public function definitions(): array
    {
        return [
            [
                'name' => 'search_documents',
                'description' => 'Busca semântica nos trechos dos documentos. Use para responder perguntas sobre o conteúdo.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'O que procurar.'],
                        'limit' => ['type' => 'integer', 'description' => 'Máximo de trechos (1 a 8).'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'get_document',
                'description' => 'Devolve os dados estruturados (tipo, resumo, partes, prazos, valores) de um documento pelo id.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['documento_id' => ['type' => 'integer']],
                    'required' => ['documento_id'],
                ],
            ],
            [
                'name' => 'list_prazos',
                'description' => 'Lista os prazos (datas) extraídos de todos os documentos prontos, em ordem cronológica.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>  resultado serializável em JSON
     */
    public function run(string $name, array $input): array
    {
        return match ($name) {
            'search_documents' => $this->searchDocuments($input),
            'get_document' => $this->getDocument($input),
            'list_prazos' => $this->listPrazos(),
            default => ['erro' => "Ferramenta desconhecida: {$name}"],
        };
    }

    private function searchDocuments(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '') {
            return ['erro' => 'query é obrigatória'];
        }
        $limit = max(1, min(8, (int) ($input['limit'] ?? 3)));

        return ['resultados' => $this->search->search($query, $limit)];
    }

    private function getDocument(array $input): array
    {
        $doc = Documento::find((int) ($input['documento_id'] ?? 0));
        if (! $doc || ! $doc->estaPronto()) {
            return ['erro' => 'Documento não encontrado ou ainda não processado.'];
        }

        return ['id' => $doc->id, 'titulo' => $doc->titulo, 'dados' => $doc->dados];
    }

    private function listPrazos(): array
    {
        $prazos = [];
        foreach (Documento::where('status', Documento::STATUS_PRONTO)->get() as $doc) {
            foreach ($doc->dados['prazos'] ?? [] as $prazo) {
                $date = Carbon::createFromFormat('d/m/Y', $prazo['data']) ?: null;
                $prazos[] = [
                    'documento_id' => $doc->id,
                    'titulo' => $doc->titulo,
                    'descricao' => $prazo['descricao'],
                    'data' => $prazo['data'],
                    '_ordem' => $date?->timestamp ?? PHP_INT_MAX,
                ];
            }
        }

        usort($prazos, fn ($a, $b) => $a['_ordem'] <=> $b['_ordem']);

        return ['prazos' => array_map(fn ($p) => array_diff_key($p, ['_ordem' => 1]), $prazos)];
    }
}
