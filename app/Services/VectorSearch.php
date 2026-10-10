<?php

namespace App\Services;

use App\Ai\Contracts\Embedder;
use App\Models\Trecho;

/**
 * Busca semântica por similaridade de cosseno sobre os embeddings dos trechos.
 * Calculada em PHP para manter o projeto simples; em escala, o lugar disso é um
 * banco vetorial (pgvector, Elasticsearch/OpenSearch etc.) atrás desta mesma classe.
 * Toda busca é restrita a um escopo (espaço de trabalho, e opcionalmente um documento).
 */
class VectorSearch
{
    public function __construct(private readonly Embedder $embedder) {}

    /**
     * @return array<int, array{trecho_id: int, documento_id: int, titulo: string, registro: string, texto: string, score: float}>
     */
    public function search(string $query, Escopo $escopo, ?int $limit = null, ?float $minScore = null): array
    {
        $limit ??= config('ia.search.limit');
        $minScore ??= config('ia.search.min_score');

        $queryVector = $this->embedder->embed($query);

        $scored = [];
        Trecho::query()
            ->with('documento:id,titulo,numero,created_at')
            ->whereHas('documento', function ($q) use ($escopo) {
                $q->where('status', 'pronto')
                    ->where('espaco_id', $escopo->espacoId)
                    ->when($escopo->documentoId, fn ($q, $id) => $q->where('id', $id));
            })
            ->lazyById()
            ->each(function (Trecho $trecho) use ($queryVector, $minScore, &$scored) {
                $score = $this->cosine($queryVector, $trecho->embedding);
                if ($score >= $minScore) {
                    $scored[] = [
                        'trecho_id' => $trecho->id,
                        'documento_id' => $trecho->documento_id,
                        'titulo' => $trecho->documento->titulo,
                        'registro' => $trecho->documento->registro(),
                        'texto' => $trecho->texto,
                        'score' => round($score, 4),
                    ];
                }
            });

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /** @param array<int, float> $a @param array<int, float> $b */
    public function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        foreach ($a as $i => $value) {
            $dot += $value * ($b[$i] ?? 0.0);
        }

        // Vetores já normalizados: o produto escalar é o cosseno.
        return $dot;
    }
}
