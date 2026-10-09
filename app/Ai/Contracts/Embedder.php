<?php

namespace App\Ai\Contracts;

interface Embedder
{
    /**
     * @return array<int, float> vetor normalizado (norma L2 = 1)
     */
    public function embed(string $text): array;
}
