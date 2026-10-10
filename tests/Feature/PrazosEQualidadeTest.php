<?php

namespace Tests\Feature;

use App\Models\Espaco;
use App\Models\PerguntaVerificacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrazosEQualidadeTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $espaco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        [, $this->espaco] = $this->entrar();
    }

    public function test_calendario_mostra_o_prazo_no_mes_certo(): void
    {
        $this->arquivar($this->espaco, 'Contrato do prazo', "Contrato.\nContratante: A\nContratada: B\n\nPagar até 10/12/2026.");

        $this->get('/prazos?mes=2026-12')->assertOk()->assertSee('Contrato do prazo', false)->assertSee('dezembro de 2026');
        $this->get('/prazos?mes=2026-11')->assertOk()->assertSee('Contrato do prazo'); // aparece na lista "A vencer" ou "Vencidos"
        $this->get('/prazos?mes=lixo')->assertSessionHasErrors('mes');
    }

    public function test_calendario_tem_uma_linha_por_semana(): void
    {
        $r = $this->get('/prazos?mes=2026-10')->assertOk();

        // Outubro/2026 ocupa 5 semanas (domingo a sábado).
        $this->assertSame(5, substr_count($r->getContent(), '<tr>') - 1);
    }

    public function test_exporta_ics_com_um_evento_por_prazo(): void
    {
        $this->arquivar($this->espaco, 'Contrato ics', "Contrato.\nContratante: A\n\nPagar até 10/12/2026, depois 15/01/2027.");

        $r = $this->get('/prazos.ics')->assertOk();

        $this->assertStringContainsString('text/calendar', $r->headers->get('Content-Type'));
        $corpo = $r->getContent();
        $this->assertSame(2, substr_count($corpo, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261210', $corpo);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20270115', $corpo);
        $this->assertStringContainsString('Contrato ics', $corpo);
    }

    public function test_painel_de_qualidade_vazio(): void
    {
        $this->get('/qualidade')->assertOk()->assertSee('Ainda não há respostas para avaliar');
    }

    public function test_indicadores_contam_respostas_fundamentadas_e_reprovadas(): void
    {
        $this->arquivar($this->espaco, 'Aluguel', 'O pagamento do aluguel vence todo dia 10.');

        $this->postJson('/api/perguntar', ['pergunta' => 'Quando vence o pagamento do aluguel?']);
        $this->postJson('/api/perguntar', ['pergunta' => 'xyzzy qwerty zzzz']);

        $this->get('/qualidade')->assertOk()
            ->assertSee('50%')
            ->assertSee('Respostas reprovadas para revisar')
            ->assertSee('Não encontrei');
    }

    public function test_conjunto_de_verificacao_adicionar_executar_e_remover(): void
    {
        $this->arquivar($this->espaco, 'Aluguel', 'O pagamento do aluguel vence todo dia 10.');

        $this->post('/qualidade/perguntas', ['pergunta' => 'Quando vence o pagamento do aluguel?'])->assertRedirect('/qualidade');
        $this->post('/qualidade/perguntas', ['pergunta' => 'xyzzy qwerty zzzz'])->assertRedirect();
        $this->post('/qualidade/perguntas', ['pergunta' => 'oi'])->assertSessionHasErrors('pergunta');

        $this->post('/qualidade/executar')->assertRedirect('/qualidade');

        $itens = $this->espaco->perguntasVerificacao()->orderBy('id')->get();
        $this->assertTrue($itens[0]->ultima_fundamentada);
        $this->assertFalse($itens[1]->ultima_fundamentada);
        $this->assertNotNull($itens[1]->executada_em);

        $this->delete("/qualidade/perguntas/{$itens[1]->id}")->assertRedirect();
        $this->assertDatabaseCount('perguntas_verificacao', 1);
    }

    public function test_nao_remove_pergunta_de_verificacao_de_outro_espaco(): void
    {
        $outro = Espaco::factory()->create();
        $item = PerguntaVerificacao::create(['espaco_id' => $outro->id, 'pergunta' => 'Pergunta alheia?']);

        $this->delete("/qualidade/perguntas/{$item->id}")->assertNotFound();
        $this->assertDatabaseHas('perguntas_verificacao', ['id' => $item->id]);
    }

    public function test_consultas_lista_historico_e_apaga_conversa(): void
    {
        $this->arquivar($this->espaco, 'Aluguel', 'O pagamento do aluguel vence todo dia 10.');
        $id = $this->postJson('/api/perguntar', ['pergunta' => 'Quando vence o pagamento?'])->json('conversa_id');

        $this->get('/consultas')->assertOk()->assertSee('Quando vence o pagamento?');
        $this->get("/consultas/{$id}")->assertOk()->assertSee('Apagar conversa');

        $this->delete("/consultas/{$id}")->assertRedirect('/consultas');
        $this->assertDatabaseCount('conversas', 0);
        $this->assertDatabaseCount('mensagens', 0);
    }
}
