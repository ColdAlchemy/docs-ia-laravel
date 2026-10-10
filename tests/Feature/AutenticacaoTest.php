<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_visitante_e_enviado_para_a_tela_de_entrada(): void
    {
        foreach (['/documentos', '/consultas', '/prazos', '/qualidade', '/espacos', '/documentos/novo'] as $rota) {
            $this->get($rota)->assertRedirect('/entrar');
        }
    }

    public function test_cadastro_cria_conta_e_um_espaco_pessoal(): void
    {
        $this->post('/cadastrar', [
            'name' => 'Ana Teste', 'email' => 'ana@exemplo.test',
            'password' => 'senha-segura-1', 'password_confirmation' => 'senha-segura-1',
        ])->assertRedirect('/documentos');

        $user = User::where('email', 'ana@exemplo.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['Espaço de Ana Teste'], $user->espacos->pluck('nome')->all());
        $this->assertNotSame('senha-segura-1', $user->password);
    }

    public function test_cadastro_valida_os_campos_com_mensagens_em_portugues(): void
    {
        User::factory()->create(['email' => 'repetido@exemplo.test']);

        $this->post('/cadastrar', [
            'name' => '', 'email' => 'repetido@exemplo.test', 'password' => 'curta', 'password_confirmation' => 'outra',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->get('/cadastrar')->assertOk();
        $this->post('/cadastrar', ['name' => 'X', 'email' => 'repetido@exemplo.test', 'password' => 'senha-segura-1', 'password_confirmation' => 'senha-segura-1'])
            ->assertSessionHasErrors(['email' => 'Já existe uma conta com esse e-mail.']);
    }

    public function test_entrar_com_credenciais_corretas(): void
    {
        $user = User::factory()->create(['password' => 'minha-senha-123']);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'minha-senha-123'])->assertRedirect('/documentos');

        $this->assertAuthenticatedAs($user);
        $this->get('/documentos')->assertOk()->assertSee('Espaço de');
    }

    public function test_entrar_com_senha_errada_nao_autentica(): void
    {
        $user = User::factory()->create(['password' => 'minha-senha-123']);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);

        $this->assertGuest();
    }

    public function test_logado_nao_ve_a_tela_de_entrada(): void
    {
        $this->entrar();

        $this->get('/entrar')->assertRedirect('/documentos');
    }

    public function test_sair_encerra_a_sessao(): void
    {
        $this->entrar();

        $this->post('/sair')->assertRedirect('/entrar');

        $this->assertGuest();
    }
}
