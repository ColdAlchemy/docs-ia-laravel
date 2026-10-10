<?php

namespace App\Services;

/**
 * Divide um texto em trechos de tamanho limitado, respeitando parágrafos e frases.
 * Cada trecho guarda onde começa e termina no texto original (em caracteres),
 * para o leitor poder mostrá-lo no lugar certo e destacá-lo quando for citado.
 */
class Chunker
{
    public function __construct(private readonly int $maxChars = 600) {}

    /** @return array<int, string> */
    public function split(string $text): array
    {
        return array_column($this->splitWithOffsets($text), 'texto');
    }

    /** @return array<int, array{texto: string, inicio: int, fim: int}> */
    public function splitWithOffsets(string $text): array
    {
        $units = [];
        foreach ($this->paragraphs($text) as [$raw, $start]) {
            $normalized = $this->normalize($raw);
            if (mb_strlen($normalized) <= $this->maxChars) {
                $units[] = ['texto' => $normalized, 'inicio' => $start, 'fim' => $start + mb_strlen($raw), 'raw' => $raw];

                continue;
            }
            // Parágrafos muito longos viram frases.
            $sentences = preg_split('/(?<=[\.\!\?])\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE) ?: [];
            foreach ($sentences as [$sentence, $byteOffset]) {
                $sentenceStart = $start + mb_strlen(substr($raw, 0, $byteOffset));
                $units[] = [
                    'texto' => $this->normalize($sentence),
                    'inicio' => $sentenceStart,
                    'fim' => $sentenceStart + mb_strlen($sentence),
                    'raw' => $sentence,
                ];
            }
        }

        // Junta unidades pequenas até o limite.
        $chunks = [];
        $current = null;
        foreach ($units as $unit) {
            if ($current !== null && mb_strlen($current['texto']) + 1 + mb_strlen($unit['texto']) > $this->maxChars) {
                $chunks[] = $current;
                $current = null;
            }
            $current = $current === null
                ? $unit + ['unica' => true]
                : ['texto' => $current['texto'].' '.$unit['texto'], 'inicio' => $current['inicio'], 'fim' => $unit['fim'], 'unica' => false];
        }
        if ($current !== null) {
            $chunks[] = $current;
        }

        // Garante o limite mesmo para frases gigantes (sempre unidades isoladas).
        $final = [];
        foreach ($chunks as $chunk) {
            if (mb_strlen($chunk['texto']) <= $this->maxChars) {
                $final[] = ['texto' => $chunk['texto'], 'inicio' => $chunk['inicio'], 'fim' => $chunk['fim']];

                continue;
            }
            $map = $this->rawIndexMap($chunk['raw']);
            foreach (mb_str_split($chunk['texto'], $this->maxChars) as $i => $piece) {
                $offset = $i * $this->maxChars;
                $lead = mb_strlen($piece) - mb_strlen(ltrim($piece));
                $trimmed = trim($piece);
                if ($trimmed === '') {
                    continue;
                }
                $first = $map[$offset + $lead] ?? 0;
                $last = $map[$offset + $lead + mb_strlen($trimmed) - 1] ?? mb_strlen($chunk['raw']) - 1;
                $final[] = ['texto' => $trimmed, 'inicio' => $chunk['inicio'] + $first, 'fim' => $chunk['inicio'] + $last + 1];
            }
        }

        return array_values($final);
    }

    /** @return array<int, array{0: string, 1: int}> parágrafos (texto aparado, posição em caracteres) */
    private function paragraphs(string $text): array
    {
        $parts = preg_split('/\R{2,}/u', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE) ?: [];
        $result = [];
        foreach ($parts as [$part, $byteOffset]) {
            $trimmed = trim($part);
            if ($trimmed === '') {
                continue;
            }
            $lead = mb_strlen($part) - mb_strlen(ltrim($part));
            $result[] = [$trimmed, mb_strlen(substr($text, 0, $byteOffset)) + $lead];
        }

        return $result;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Para cada caractere do texto normalizado, a posição do caractere correspondente no original.
     *
     * @return array<int, int>
     */
    private function rawIndexMap(string $raw): array
    {
        $map = [];
        $previousWasSpace = true; // o texto normalizado não começa com espaço
        foreach (mb_str_split($raw) as $i => $char) {
            if (preg_match('/^\s$/u', $char)) {
                if (! $previousWasSpace) {
                    $map[] = $i;
                }
                $previousWasSpace = true;
            } else {
                $map[] = $i;
                $previousWasSpace = false;
            }
        }

        return $map;
    }
}
