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

    public function test_posicoes_apontam_para_o_texto_original(): void
    {
        $texto = "Ação inicial: João   pagou R\$ 10,00.\nSegunda linha.\n\n   Segundo parágrafo, com acentuação: é útil.\n\n".str_repeat('Frase longa de teste. ', 20);

        foreach ((new Chunker(80))->splitWithOffsets($texto) as $c) {
            $original = mb_substr($texto, $c['inicio'], $c['fim'] - $c['inicio']);
            $this->assertSame($c['texto'], trim(preg_replace('/\s+/u', ' ', $original)));
        }
    }

    public function test_posicoes_de_uma_frase_gigante_cortada_em_pedacos(): void
    {
        $texto = 'Início '.str_repeat('palavra  ', 60).'fim sem ponto';

        $pedacos = (new Chunker(50))->splitWithOffsets($texto);

        $this->assertGreaterThan(1, count($pedacos));
        foreach ($pedacos as $c) {
            $this->assertLessThanOrEqual(50, mb_strlen($c['texto']));
            $this->assertSame($c['texto'], trim(preg_replace('/\s+/u', ' ', mb_substr($texto, $c['inicio'], $c['fim'] - $c['inicio']))));
        }
    }
}
