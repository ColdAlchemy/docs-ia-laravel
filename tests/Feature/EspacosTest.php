<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Espaco;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** O ponto mais importante do multiusuário: um espaço nunca enxerga os dados de outro. */
class EspacosTest extends TestCase
{
    use RefreshDatabase;

    private const SEGREDO = 'A senha do cofre da empresa Alfa é abacaxi azul e vence em 05/01/2027.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return array{0: User, 1: Espaco, 2: Documento} dono de um espaço com um documento sigiloso */
    private function donoComSegredo(): array
    {
        $dono = User::factory()->create();
        $espaco = Espaco::factory()->create(['dono_id' => $dono->id]);

        return [$dono, $espaco, $this->arquivar($espaco, 'Cofre Alfa', self::SEGREDO)];
    }

    public function test_outro_usuario_nao_abre_nem_altera_documento_alheio(): void
    {
        [, , $doc] = $this->donoComSegredo();
        $this->entrar();

        $this->get("/documentos/{$doc->id}")->assertNotFound();
        $this->get("/documentos/{$doc->id}/relatorio")->assertNotFound();
        $this->post("/documentos/{$doc->id}/reprocessar")->assertNotFound();
        $this->delete("/documentos/{$doc->id}")->assertNotFound();
        $this->assertDatabaseHas('documentos', ['id' => $doc->id]);
    }

    public function test_listagem_busca_e_agente_nao_vazam_entre_espacos(): void
    {
        $this->donoComSegredo();
        [, $meu] = $this->entrar();
        $this->arquivar($meu, 'Meu manual', 'Texto sobre outro assunto, férias e escala.');

        $this->get('/documentos')->assertSee('Meu manual')->assertDontSee('Cofre Alfa');

        $busca = $this->getJson('/api/busca?q=senha do cofre abacaxi')->json('resultados');
        $this->assertSame([], array_filter($busca, fn ($r) => str_contains($r['texto'], 'abacaxi')));

        $resposta = $this->postJson('/api/perguntar', ['pergunta' => 'Qual é a senha do cofre da empresa Alfa?'])->json('mensagem');
        $this->assertStringNotContainsString('abacaxi', $resposta['conteudo']);
        $this->assertSame([], $resposta['fontes']);
    }

    public function test_prazos_de_outro_espaco_nao_aparecem(): void
    {
        $this->donoComSegredo();
        $this->entrar();

        $this->get('/prazos?mes=2027-01')->assertOk()->assertDontSee('Cofre Alfa');
        $this->assertStringNotContainsString('SUMMARY', $this->get('/prazos.ics')->getContent());
    }

    public function test_nao_pode_perguntar_sobre_documento_de_outro_espaco(): void
    {
        [, , $doc] = $this->donoComSegredo();
        $this->entrar();

        $this->postJson('/api/perguntar', ['pergunta' => 'Qual a senha?', 'documento_id' => $doc->id])->assertNotFound();
    }

    public function test_membro_adicionado_passa_a_ver_os_documentos(): void
    {
        [$dono, $espaco] = $this->donoComSegredo();
        $colega = User::factory()->create(['email' => 'colega@exemplo.test']);

        $this->actingAs($dono)->withSession(['espaco_id' => $espaco->id])
            ->post("/espacos/{$espaco->id}/membros", ['email' => 'colega@exemplo.test'])->assertSessionHasNoErrors();

        $this->actingAs($colega)->post("/espacos/{$espaco->id}/trocar")->assertRedirect('/documentos');
        $this->get('/documentos')->assertSee('Cofre Alfa');
    }

    public function test_so_o_dono_gerencia_membros(): void
    {
        [$dono, $espaco] = $this->donoComSegredo();
        $colega = User::factory()->create();
        $espaco->membros()->attach($colega->id);
        $intruso = User::factory()->create(['email' => 'novo@exemplo.test']);

        $this->actingAs($colega)->post("/espacos/{$espaco->id}/membros", ['email' => 'novo@exemplo.test'])->assertForbidden();
        $this->actingAs($intruso)->post("/espacos/{$espaco->id}/membros", ['email' => 'novo@exemplo.test'])->assertNotFound();
        $this->actingAs($colega)->delete("/espacos/{$espaco->id}/membros/{$dono->id}")->assertForbidden();
        $this->assertFalse($intruso->participaDe($espaco->id));
    }

    public function test_adicionar_membro_valida_email_inexistente_e_repetido(): void
    {
        [$dono, $espaco] = $this->donoComSegredo();
        $this->actingAs($dono)->withSession(['espaco_id' => $espaco->id]);

        $this->post("/espacos/{$espaco->id}/membros", ['email' => 'ninguem@exemplo.test'])->assertSessionHasErrors('email');
        $this->post("/espacos/{$espaco->id}/membros", ['email' => $dono->email])->assertSessionHasErrors('email');
    }

    public function test_dono_nao_pode_ser_removido_e_membro_removido_perde_acesso(): void
    {
        [$dono, $espaco, $doc] = $this->donoComSegredo();
        $colega = User::factory()->create();
        $espaco->membros()->attach($colega->id);

        $this->actingAs($dono)->withSession(['espaco_id' => $espaco->id])
            ->delete("/espacos/{$espaco->id}/membros/{$dono->id}")->assertStatus(422);
        $this->delete("/espacos/{$espaco->id}/membros/{$colega->id}")->assertRedirect();

        $this->actingAs($colega)->withSession(['espaco_id' => $espaco->id])->get("/documentos/{$doc->id}")->assertNotFound();
    }

    public function test_criar_e_trocar_de_espaco(): void
    {
        [$user, $primeiro] = $this->entrar();

        $this->post('/espacos', ['nome' => 'Segundo espaço'])->assertRedirect('/documentos');
        $segundo = Espaco::where('nome', 'Segundo espaço')->firstOrFail();
        $this->assertTrue($user->participaDe($segundo->id));
        $this->get('/documentos')->assertSee('Segundo espaço');

        $this->post("/espacos/{$primeiro->id}/trocar")->assertRedirect();
        $this->assertSame($primeiro->id, session('espaco_id'));
        $this->post('/espacos', [])->assertSessionHasErrors('nome');
    }
}
