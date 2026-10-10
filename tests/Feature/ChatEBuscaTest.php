<?php

namespace Tests\Feature;

use App\Models\Conversa;
use App\Models\Documento;
use App\Models\Espaco;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatEBuscaTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $espaco;

    private User $user;

    private Documento $aluguel;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->user, $this->espaco] = $this->entrar();
        $this->aluguel = $this->arquivar($this->espaco, 'Contrato de aluguel', "O pagamento do aluguel deve ocorrer até 10/12/2026.\n\nA multa por atraso no pagamento é de R\$ 300,00.");
        $this->arquivar($this->espaco, 'Manual do jardim', 'A poda das árvores e a manutenção da piscina acontecem todo mês de março.');
    }

    public function test_exige_login(): void
    {
        auth()->logout();

        $this->postJson('/api/perguntar', ['pergunta' => 'Qual o prazo?'])->assertUnauthorized();
        $this->getJson('/api/busca?q=aluguel')->assertUnauthorized();
    }

    public function test_busca_semantica_ordena_pelo_documento_relevante(): void
    {
        $r = $this->getJson('/api/busca?q=prazo de pagamento do aluguel')->assertOk()->json('resultados');

        $this->assertNotEmpty($r);
        $this->assertSame('Contrato de aluguel', $r[0]['titulo']);
        $this->assertSame('0001/'.now()->year, $r[0]['registro']);
    }

    public function test_busca_ignora_documentos_ainda_nao_processados(): void
    {
        Documento::create(['espaco_id' => $this->espaco->id, 'titulo' => 'Pendente', 'conteudo' => 'aluguel aluguel aluguel', 'status' => 'pendente']);

        $titulos = array_column($this->getJson('/api/busca?q=aluguel')->json('resultados'), 'titulo');

        $this->assertNotContains('Pendente', $titulos);
    }

    public function test_pergunta_responde_com_fontes_passos_e_avaliacao_fundamentada(): void
    {
        $r = $this->postJson('/api/perguntar', ['pergunta' => 'Qual o prazo de pagamento do aluguel?'])->assertOk()->json();

        $this->assertStringContainsString('10/12/2026', $r['mensagem']['conteudo']);
        $this->assertSame('search_documents', $r['mensagem']['passos'][0]['ferramenta']);
        $this->assertNotEmpty($r['mensagem']['fontes']);
        $this->assertTrue($r['mensagem']['avaliacao']['fundamentada']);
    }

    public function test_pergunta_guarda_conversa_e_mensagens(): void
    {
        $r = $this->postJson('/api/perguntar', ['pergunta' => 'Qual a multa por atraso?'])->json();

        $conversa = Conversa::findOrFail($r['conversa_id']);
        $this->assertSame($this->user->id, $conversa->user_id);
        $this->assertSame($this->espaco->id, $conversa->espaco_id);
        $this->assertSame(['user', 'assistant'], $conversa->mensagens->pluck('papel')->all());
        $this->assertTrue($conversa->mensagens->last()->fundamentada);
    }

    public function test_continua_a_conversa_usando_o_historico_do_banco(): void
    {
        $id = $this->postJson('/api/perguntar', ['pergunta' => 'Qual a multa por atraso?'])->json('conversa_id');
        $this->postJson('/api/perguntar', ['pergunta' => 'E o prazo de pagamento?', 'conversa_id' => $id])->assertOk();

        $this->assertDatabaseCount('conversas', 1);
        $this->assertSame(4, Mensagem::where('conversa_id', $id)->count());
    }

    public function test_pergunta_no_leitor_fica_restrita_ao_documento(): void
    {
        $jardim = $this->espaco->documentos()->where('titulo', 'Manual do jardim')->first();

        $r = $this->postJson('/api/perguntar', ['pergunta' => 'Qual o prazo de pagamento do aluguel?', 'documento_id' => $jardim->id])->json('mensagem');

        $this->assertStringContainsString('Não encontrei', $r['conteudo']);
        $this->assertFalse($r['avaliacao']['fundamentada']);
    }

    public function test_pergunta_sem_informacao_relevante_admite_que_nao_encontrou(): void
    {
        $r = $this->postJson('/api/perguntar', ['pergunta' => 'xyzzy qwerty zzzz'])->assertOk()->json('mensagem');

        $this->assertStringContainsString('Não encontrei', $r['conteudo']);
        $this->assertFalse($r['avaliacao']['fundamentada']);
    }

    public function test_valida_a_entrada(): void
    {
        $this->postJson('/api/perguntar', [])->assertStatus(422)->assertJsonValidationErrors('pergunta');
        $this->postJson('/api/perguntar', ['pergunta' => 'ok'])->assertStatus(422);
    }

    public function test_nao_usa_conversa_de_outro_usuario(): void
    {
        $id = $this->postJson('/api/perguntar', ['pergunta' => 'Qual a multa por atraso?'])->json('conversa_id');

        $colega = User::factory()->create();
        $this->espaco->membros()->attach($colega->id);
        $this->actingAs($colega)->withSession(['espaco_id' => $this->espaco->id]);

        $this->postJson('/api/perguntar', ['pergunta' => 'Qual o prazo?', 'conversa_id' => $id])->assertNotFound();
        $this->get("/consultas/{$id}")->assertNotFound();
    }
}
