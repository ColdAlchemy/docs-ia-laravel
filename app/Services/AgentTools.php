<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Prazo;

/**
 * Ferramentas que o agente pode chamar (tool calling). Cada ferramenta tem:
 * a definição enviada ao LLM (nome, descrição, JSON Schema) e a execução em PHP.
 */
class AgentTools
{
    public function __construct(
        private readonly VectorSearch $search,
        private readonly ?Escopo $escopo = null,
    ) {}

    /** Cópia da caixa de ferramentas restrita a um espaço (e talvez a um documento). */
    public function comEscopo(Escopo $escopo): self
    {
        return new self($this->search, $escopo);
    }

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
                'description' => 'Lista os prazos (datas) extraídos dos documentos prontos, em ordem cronológica.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> resultado serializável em JSON
     */
    public function run(string $name, array $input): array
    {
        if ($this->escopo === null) {
            return ['erro' => 'Ferramentas sem escopo definido.'];
        }

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

        return ['resultados' => $this->search->search($query, $this->escopo, $limit)];
    }

    private function getDocument(array $input): array
    {
        $doc = Documento::where('espaco_id', $this->escopo->espacoId)
            ->when($this->escopo->documentoId, fn ($q, $id) => $q->where('id', $id))
            ->find((int) ($input['documento_id'] ?? 0));
        if (! $doc || ! $doc->estaPronto()) {
            return ['erro' => 'Documento não encontrado ou ainda não processado.'];
        }

        return ['id' => $doc->id, 'titulo' => $doc->titulo, 'dados' => $doc->dados];
    }

    private function listPrazos(): array
    {
        $prazos = Prazo::with('documento:id,titulo')
            ->where('espaco_id', $this->escopo->espacoId)
            ->when($this->escopo->documentoId, fn ($q, $id) => $q->where('documento_id', $id))
            ->orderBy('data')
            ->get()
            ->map(fn (Prazo $p) => [
                'documento_id' => $p->documento_id,
                'titulo' => $p->documento->titulo,
                'descricao' => $p->descricao,
                'data' => $p->data->format('d/m/Y'),
            ])
            ->all();

        return ['prazos' => $prazos];
    }
}
