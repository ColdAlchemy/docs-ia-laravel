<?php

namespace Tests\Feature;

use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const TEXTO = "Contrato de prestação de serviços.\nContratante: Maria Souza\nContratada: Tech Ltda\n\nO pagamento de R\$ 1.500,00 deve ocorrer até 10/12/2026.\n\nO contrato termina em 31/12/2027.";

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_envio_de_documento_indexa_trechos_e_extrai_dados(): void
    {
        $this->post('/documentos', ['titulo' => 'Contrato de teste', 'conteudo' => self::TEXTO])
            ->assertRedirect();

        $doc = Documento::first();
        $this->assertSame(Documento::STATUS_PRONTO, $doc->status);
        $this->assertGreaterThan(0, $doc->trechos()->count());
        $this->assertSame('contrato', $doc->dados['tipo']);
        $this->assertSame(['Maria Souza', 'Tech Ltda'], $doc->dados['partes']);
        $this->assertSame('1.500,00', $doc->dados['valores'][0]['valor']);
        $this->assertContains('10/12/2026', array_column($doc->dados['prazos'], 'data'));
    }

    public function test_aceita_upload_de_arquivo_txt(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('doc.txt', self::TEXTO);

        $this->post('/documentos', ['titulo' => 'Via arquivo', 'arquivo' => $arquivo])->assertRedirect();

        $this->assertStringContainsString('Tech Ltda', Documento::first()->conteudo);
    }

    public function test_exige_titulo_e_algum_conteudo(): void
    {
        $this->post('/documentos', [])->assertSessionHasErrors(['titulo', 'conteudo']);
        $this->post('/documentos', ['titulo' => 'x'])->assertSessionHasErrors('conteudo');
    }

    public function test_rejeita_arquivo_de_tipo_invalido(): void
    {
        $this->post('/documentos', ['titulo' => 'x', 'arquivo' => UploadedFile::fake()->create('a.exe', 10)])
            ->assertSessionHasErrors('arquivo');
    }

    public function test_listagem_e_detalhe_mostram_o_documento(): void
    {
        $this->post('/documentos', ['titulo' => 'Contrato visível', 'conteudo' => self::TEXTO]);
        $doc = Documento::first();

        $this->get('/documentos')->assertOk()->assertSee('Contrato visível');
        $this->get("/documentos/{$doc->id}")->assertOk()->assertSee('Maria Souza')->assertSee('10/12/2026');
    }

    public function test_reprocessar_nao_duplica_trechos(): void
    {
        $this->post('/documentos', ['titulo' => 'x', 'conteudo' => self::TEXTO]);
        $doc = Documento::first();
        $antes = $doc->trechos()->count();

        $this->post("/documentos/{$doc->id}/reprocessar")->assertRedirect();

        $this->assertSame($antes, $doc->trechos()->count());
    }

    public function test_excluir_remove_documento_e_trechos(): void
    {
        $this->post('/documentos', ['titulo' => 'x', 'conteudo' => self::TEXTO]);
        $doc = Documento::first();

        $this->delete("/documentos/{$doc->id}")->assertRedirect('/documentos');

        $this->assertDatabaseCount('documentos', 0);
        $this->assertDatabaseCount('trechos', 0);
    }
}
