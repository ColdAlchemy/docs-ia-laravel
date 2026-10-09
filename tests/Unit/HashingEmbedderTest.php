<?php

namespace Tests\Unit;

use App\Ai\HashingEmbedder;
use PHPUnit\Framework\TestCase;

class HashingEmbedderTest extends TestCase
{
    public function test_vetor_tem_norma_um_e_dimensao_correta(): void
    {
        $v = (new HashingEmbedder(64))->embed('Contrato de aluguel residencial');

        $this->assertCount(64, $v);
        $this->assertEqualsWithDelta(1.0, sqrt(array_sum(array_map(fn ($x) => $x * $x, $v))), 1e-9);
    }

    public function test_textos_parecidos_ficam_mais_proximos_que_textos_diferentes(): void
    {
        $e = new HashingEmbedder(256);
        $cos = fn ($a, $b) => array_sum(array_map(fn ($x, $y) => $x * $y, $a, $b));

        $base = $e->embed('prazo de pagamento do aluguel');
        $perto = $e->embed('pagamento do aluguel deve ocorrer no prazo');
        $longe = $e->embed('manutenção do jardim e da piscina');

        $this->assertGreaterThan($cos($base, $longe), $cos($base, $perto));
    }

    public function test_ignora_acentos_e_caixa(): void
    {
        $e = new HashingEmbedder(128);

        $this->assertSame($e->embed('NOTIFICAÇÃO'), $e->embed('notificacao'));
    }

    public function test_texto_sem_palavras_uteis_gera_vetor_zerado(): void
    {
        $this->assertSame(array_fill(0, 8, 0.0), (new HashingEmbedder(8))->embed('de a o'));
    }
}
