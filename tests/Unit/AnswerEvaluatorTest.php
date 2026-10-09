<?php

namespace Tests\Unit;

use App\Services\AnswerEvaluator;
use PHPUnit\Framework\TestCase;

class AnswerEvaluatorTest extends TestCase
{
    public function test_resposta_com_citacoes_validas_e_fundamentada(): void
    {
        $r = (new AnswerEvaluator)->evaluate('O prazo é 10/12/2026 [T3] e [T5].', [3, 5, 9]);

        $this->assertTrue($r['fundamentada']);
        $this->assertSame([3, 5], $r['citacoes']);
    }

    public function test_resposta_sem_citacao_nao_e_fundamentada(): void
    {
        $r = (new AnswerEvaluator)->evaluate('O prazo é 10/12/2026.', [3]);

        $this->assertFalse($r['fundamentada']);
    }

    public function test_citacao_de_trecho_nao_recuperado_e_invalida(): void
    {
        $r = (new AnswerEvaluator)->evaluate('Segundo o documento [T99].', [3]);

        $this->assertFalse($r['fundamentada']);
        $this->assertSame([99], $r['invalidas']);
    }
}
