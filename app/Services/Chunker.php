<?php

namespace App\Services;

/**
 * Divide um texto em trechos de tamanho limitado, respeitando parágrafos e frases.
 */
class Chunker
{
    public function __construct(private readonly int $maxChars = 600) {}

    /** @return array<int, string> */
    public function split(string $text): array
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Parágrafos muito longos viram frases.
        $units = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim(preg_replace('/\s+/', ' ', $paragraph));
            if (mb_strlen($paragraph) <= $this->maxChars) {
                $units[] = $paragraph;
                continue;
            }
            foreach (preg_split('/(?<=[\.\!\?])\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY) as $sentence) {
                $units[] = $sentence;
            }
        }

        // Junta unidades pequenas até o limite.
        $chunks = [];
        $current = '';
        foreach ($units as $unit) {
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($unit) > $this->maxChars) {
                $chunks[] = $current;
                $current = '';
            }
            $current = $current === '' ? $unit : $current.' '.$unit;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        // Garante o limite mesmo para frases gigantes.
        $final = [];
        foreach ($chunks as $chunk) {
            foreach (mb_str_split($chunk, $this->maxChars) as $piece) {
                $final[] = trim($piece);
            }
        }

        return array_values(array_filter($final, fn ($c) => $c !== ''));
    }
}
