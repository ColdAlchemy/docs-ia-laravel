<?php

namespace App\Services;

/**
 * Avaliação simples da qualidade da resposta (groundedness):
 * a resposta deve citar trechos no formato [T<id>] e todas as citações precisam
 * corresponder a trechos realmente recuperados nesta conversa.
 */
class AnswerEvaluator
{
    /**
     * @param  array<int, int>  $retrievedIds  ids de trechos recuperados pelas ferramentas
     * @return array{fundamentada: bool, citacoes: array<int, int>, invalidas: array<int, int>, motivo: string}
     */
    public function evaluate(string $answer, array $retrievedIds): array
    {
        preg_match_all('/\[T(\d+)\]/', $answer, $matches);
        $cited = array_values(array_unique(array_map('intval', $matches[1])));
        $invalid = array_values(array_diff($cited, $retrievedIds));

        if ($cited === []) {
            return ['fundamentada' => false, 'citacoes' => [], 'invalidas' => [], 'motivo' => 'A resposta não cita nenhum trecho.'];
        }
        if ($invalid !== []) {
            return ['fundamentada' => false, 'citacoes' => $cited, 'invalidas' => $invalid, 'motivo' => 'A resposta cita trechos que não foram recuperados.'];
        }

        return ['fundamentada' => true, 'citacoes' => $cited, 'invalidas' => [], 'motivo' => 'Todas as citações correspondem a trechos recuperados.'];
    }
}
