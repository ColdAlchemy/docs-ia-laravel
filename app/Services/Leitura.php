<?php

namespace App\Services;

use App\Models\Documento;

/**
 * Monta o que o leitor mostra: o texto original dividido em blocos (um por trecho indexado),
 * cada bloco com as anotações de margem (prazos, valores e partes) que aparecem nele.
 */
class Leitura
{
    /**
     * @return array<int, array{id: ?int, texto: string, notas: array<int, array{tipo: string, rotulo: string, detalhe: string}>}>
     */
    public function blocos(Documento $documento): array
    {
        $trechos = $documento->trechos()->get();
        $conteudo = $documento->conteudo;

        // Sem trechos (ainda não processado) ou sem posições: o texto inteiro em um bloco só.
        if ($trechos->isEmpty() || $trechos->contains(fn ($t) => $t->inicio === null)) {
            return [['id' => null, 'texto' => $conteudo, 'notas' => $this->notas($documento, $conteudo)]];
        }

        return $trechos->map(function ($t) use ($documento, $conteudo) {
            $texto = mb_substr($conteudo, $t->inicio, $t->fim - $t->inicio);

            return ['id' => $t->id, 'texto' => $texto, 'notas' => $this->notas($documento, $texto)];
        })->all();
    }

    /** Anotações cujo texto literal (data, valor ou nome) aparece neste bloco. */
    private function notas(Documento $documento, string $texto): array
    {
        $notas = [];
        foreach ($documento->dados['prazos'] ?? [] as $prazo) {
            if (str_contains($texto, $prazo['data'])) {
                $notas[] = ['tipo' => 'prazo', 'rotulo' => $prazo['data'], 'detalhe' => $prazo['descricao']];
            }
        }
        foreach ($documento->dados['valores'] ?? [] as $valor) {
            if (str_contains($texto, $valor['valor'])) {
                $notas[] = ['tipo' => 'valor', 'rotulo' => 'R$ '.$valor['valor'], 'detalhe' => $valor['descricao']];
            }
        }
        foreach ($documento->dados['partes'] ?? [] as $parte) {
            if ($parte !== '' && str_contains($texto, $parte)) {
                $notas[] = ['tipo' => 'parte', 'rotulo' => $parte, 'detalhe' => 'Parte identificada'];
            }
        }

        return $notas;
    }
}
