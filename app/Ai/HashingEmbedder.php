<?php

namespace App\Ai;

use App\Ai\Contracts\Embedder;
use Illuminate\Support\Str;

/**
 * Embedder local e determinístico (feature hashing de palavras).
 * Não precisa de rede nem de chave de API; serve para dev, testes e demonstração.
 * Em produção, troque por um embedder de provedor (basta outra implementação de Embedder).
 */
class HashingEmbedder implements Embedder
{
    private const STOPWORDS = [
        'a', 'o', 'as', 'os', 'um', 'uma', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas',
        'e', 'ou', 'que', 'com', 'por', 'para', 'ao', 'aos', 'se', 'sua', 'seu', 'sao', 'foi', 'ser', 'qual', 'quais',
    ];

    public function __construct(private readonly int $dims = 256) {}

    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dims, 0.0);

        foreach ($this->tokens($text) as $token => $count) {
            $index = crc32($token) % $this->dims;
            $sign = (crc32('s'.$token) & 1) ? 1.0 : -1.0;
            $vector[$index] += $sign * (1 + log($count));
        }

        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector)));
        if ($norm > 0) {
            $vector = array_map(fn ($v) => $v / $norm, $vector);
        }

        return $vector;
    }

    /**
     * @return array<string, int> palavra normalizada => ocorrências
     */
    public function tokens(string $text): array
    {
        $ascii = Str::lower(Str::ascii($text));
        $words = preg_split('/[^a-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY);
        $counts = [];
        foreach ($words as $word) {
            if (strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }

        return $counts;
    }
}
