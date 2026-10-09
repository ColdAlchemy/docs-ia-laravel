<?php

namespace Tests\Feature;

use App\Jobs\ProcessarDocumento;
use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatEBuscaTest extends TestCase
{
    use RefreshDatabase;

    private function enviar(string $titulo, string $texto): Documento
    {
        $doc = Documento::create(['titulo' => $titulo, 'conteudo' => $texto]);
        ProcessarDocumento::dispatchSync($doc);

        return $doc->refresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->enviar('Contrato de aluguel', "O pagamento do aluguel deve ocorrer até 10/12/2026.\n\nA multa por atraso no pagamento é de R\$ 300,00.");
        $this->enviar('Manual do jardim', 'A poda das árvores e a manutenção da piscina acontecem todo mês de março.');
    }

    public function test_busca_semantica_ordena_pelo_documento_relevante(): void
    {
        $r = $this->getJson('/api/busca?q=prazo de pagamento do aluguel')->assertOk()->json('resultados');

        $this->assertNotEmpty($r);
        $this->assertSame('Contrato de aluguel', $r[0]['titulo']);
    }

    public function test_busca_ignora_documentos_ainda_nao_processados(): void
    {
        Documento::create(['titulo' => 'Pendente', 'conteudo' => 'aluguel aluguel aluguel', 'status' => 'pendente']);

        $titulos = array_column($this->getJson('/api/busca?q=aluguel')->json('resultados'), 'titulo');

        $this->assertNotContains('Pendente', $titulos);
    }

    public function test_chat_responde_com_fontes_passos_e_avaliacao_fundamentada(): void
    {
        $r = $this->postJson('/api/chat', ['pergunta' => 'Qual o prazo de pagamento do aluguel?'])->assertOk()->json();

        $this->assertStringContainsString('10/12/2026', $r['resposta']);
        $this->assertSame('search_documents', $r['passos'][0]['ferramenta']);
        $this->assertNotEmpty($r['fontes']);
        $this->assertTrue($r['avaliacao']['fundamentada']);
    }

    public function test_chat_sem_informacao_relevante_admite_que_nao_encontrou(): void
    {
        $r = $this->postJson('/api/chat', ['pergunta' => 'xyzzy qwerty zzzz'])->assertOk()->json();

        $this->assertStringContainsString('Não encontrei', $r['resposta']);
        $this->assertFalse($r['avaliacao']['fundamentada']);
    }

    public function test_chat_valida_a_entrada(): void
    {
        $this->postJson('/api/chat', [])->assertStatus(422)->assertJsonValidationErrors('pergunta');
        $this->postJson('/api/chat', ['pergunta' => 'ok', 'historico' => [['role' => 'system', 'content' => 'x']]])
            ->assertStatus(422);
    }
}
