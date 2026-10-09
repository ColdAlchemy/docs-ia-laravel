<?php

namespace Tests\Unit;

use App\Services\Chunker;
use PHPUnit\Framework\TestCase;

class ChunkerTest extends TestCase
{
    public function test_junta_paragrafos_pequenos_ate_o_limite(): void
    {
        $chunks = (new Chunker(60))->split("Primeiro parágrafo curto.\n\nSegundo curto.\n\nTerceiro parágrafo longo o bastante para não caber junto.");

        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('Primeiro', $chunks[0]);
        $this->assertStringContainsString('Segundo', $chunks[0]);
    }

    public function test_nenhum_trecho_passa_do_limite(): void
    {
        $texto = str_repeat('Frase de teste com palavras. ', 100);

        foreach ((new Chunker(120))->split($texto) as $chunk) {
            $this->assertLessThanOrEqual(120, mb_strlen($chunk));
        }
    }

    public function test_texto_vazio_gera_zero_trechos(): void
    {
        $this->assertSame([], (new Chunker)->split("  \n\n  "));
    }
}
