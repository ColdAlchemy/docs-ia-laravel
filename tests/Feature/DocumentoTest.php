<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Prazo;
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

    /** PDF mínimo, escrito à mão, com uma linha de texto (só ASCII). */
    private function pdfComTexto(string $linha): string
    {
        $fluxo = "BT /F1 12 Tf 72 720 Td ({$linha}) Tj ET";
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($fluxo)." >>\nstream\n{$fluxo}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $posicoes = [];
        foreach ($objetos as $i => $objeto) {
            $posicoes[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$objeto}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($posicoes as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }

        return $pdf."trailer\n<< /Size ".(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    public function test_envio_de_documento_indexa_trechos_e_extrai_dados(): void
    {
        [$user, $espaco] = $this->entrar();

        $this->post('/documentos', ['titulo' => 'Contrato de teste', 'conteudo' => self::TEXTO])->assertRedirect();

        $doc = Documento::first();
        $this->assertSame($espaco->id, $doc->espaco_id);
        $this->assertSame($user->id, $doc->user_id);
        $this->assertSame(1, $doc->numero);
        $this->assertSame(Documento::STATUS_PRONTO, $doc->status);
        $this->assertGreaterThan(0, $doc->trechos()->count());
        $this->assertSame('contrato', $doc->dados['tipo']);
        $this->assertSame(['Maria Souza', 'Tech Ltda'], $doc->dados['partes']);
        $this->assertSame('1.500,00', $doc->dados['valores'][0]['valor']);
        $this->assertContains('10/12/2026', array_column($doc->dados['prazos'], 'data'));
    }

    public function test_numero_de_registro_e_sequencial_por_espaco(): void
    {
        [, $espaco] = $this->entrar();

        $this->post('/documentos', ['titulo' => 'Um', 'conteudo' => self::TEXTO]);
        $this->post('/documentos', ['titulo' => 'Dois', 'conteudo' => self::TEXTO]);

        $this->assertSame([1, 2], $espaco->documentos()->orderBy('numero')->pluck('numero')->all());
        $this->assertMatchesRegularExpression('#^0002/\d{4}$#', $espaco->documentos()->where('numero', 2)->first()->registro());
    }

    public function test_trechos_guardam_a_posicao_no_texto_original(): void
    {
        [, $espaco] = $this->entrar();
        $doc = $this->arquivar($espaco, 'Posições', self::TEXTO);

        foreach ($doc->trechos as $trecho) {
            $original = mb_substr($doc->conteudo, $trecho->inicio, $trecho->fim - $trecho->inicio);
            $this->assertSame($trecho->texto, preg_replace('/\s+/u', ' ', $original));
        }
    }

    public function test_prazos_extraidos_viram_registros_com_data(): void
    {
        [, $espaco] = $this->entrar();
        $doc = $this->arquivar($espaco, 'Prazos', self::TEXTO."\n\nPrazo inválido: 31/02/2026.");

        $datas = $doc->prazos->map(fn (Prazo $p) => $p->data->format('Y-m-d'))->all();
        $this->assertSame(['2026-12-10', '2027-12-31'], $datas);
    }

    public function test_aceita_upload_de_arquivo_txt(): void
    {
        [, $espaco] = $this->entrar();
        $arquivo = UploadedFile::fake()->createWithContent('doc.txt', self::TEXTO);

        $this->post('/documentos', ['titulo' => 'Via arquivo', 'arquivo' => $arquivo])->assertRedirect();

        $doc = $espaco->documentos()->first();
        $this->assertStringContainsString('Tech Ltda', $doc->conteudo);
        $this->assertSame('arquivo', $doc->origem);
    }

    public function test_aceita_upload_de_pdf_com_texto(): void
    {
        [, $espaco] = $this->entrar();
        $pdf = UploadedFile::fake()->createWithContent('contrato.pdf', $this->pdfComTexto('Contrato de teste. Pagamento ate 10/12/2026.'));

        $this->post('/documentos', ['titulo' => 'Via PDF', 'arquivo' => $pdf])->assertRedirect();

        $doc = $espaco->documentos()->first();
        $this->assertSame('pdf', $doc->origem);
        $this->assertSame('contrato.pdf', $doc->arquivo_nome);
        $this->assertStringContainsString('Pagamento ate 10/12/2026', $doc->conteudo);
        $this->assertSame('2026-12-10', $doc->prazos->first()->data->format('Y-m-d'));
    }

    public function test_pdf_sem_texto_devolve_erro_claro(): void
    {
        $this->entrar();
        $vazio = UploadedFile::fake()->createWithContent('imagem.pdf', $this->pdfComTexto(''));

        $this->post('/documentos', ['titulo' => 'Sem texto', 'arquivo' => $vazio])
            ->assertSessionHasErrors('arquivo');
        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_exige_titulo_e_algum_conteudo(): void
    {
        $this->entrar();

        $this->post('/documentos', [])->assertSessionHasErrors(['titulo', 'conteudo']);
        $this->post('/documentos', ['titulo' => 'x'])->assertSessionHasErrors('conteudo');
    }

    public function test_rejeita_arquivo_de_tipo_invalido(): void
    {
        $this->entrar();

        $this->post('/documentos', ['titulo' => 'x', 'arquivo' => UploadedFile::fake()->create('a.exe', 10)])
            ->assertSessionHasErrors('arquivo');
    }

    public function test_listagem_e_leitor_mostram_o_documento_com_anotacoes(): void
    {
        [, $espaco] = $this->entrar();
        $doc = $this->arquivar($espaco, 'Contrato visível', self::TEXTO);

        $this->get('/documentos')->assertOk()->assertSee('Contrato visível');
        $this->get("/documentos/{$doc->id}")
            ->assertOk()
            ->assertSee('Maria Souza')
            ->assertSee('10/12/2026')
            ->assertSee('Parte identificada')
            ->assertSee('Pergunte a este documento');
    }

    public function test_leitor_destaca_o_trecho_pedido_na_url(): void
    {
        [, $espaco] = $this->entrar();
        $doc = $this->arquivar($espaco, 'Destaque', self::TEXTO);
        $trecho = $doc->trechos->last();

        $this->get("/documentos/{$doc->id}?trecho={$trecho->id}")
            ->assertOk()
            ->assertSee("id=\"T{$trecho->id}\"", false)
            ->assertSeeInOrder(['bloco marcado', "id=\"T{$trecho->id}\""], false);
    }

    public function test_filtros_da_listagem(): void
    {
        [, $espaco] = $this->entrar();
        $this->arquivar($espaco, 'Contrato alfa', self::TEXTO);
        $this->arquivar($espaco, 'Notificação beta', "Notificação extrajudicial.\nNotificante: A\nNotificado: B");

        $this->get('/documentos?q=alfa')->assertSee('Contrato alfa')->assertDontSee('Notificação beta');
        $this->get('/documentos?tipo=notificacao')->assertSee('Notificação beta')->assertDontSee('Contrato alfa');
        $this->get('/documentos?tipo=invalido')->assertSessionHasErrors('tipo');
    }

    public function test_relatorio_traz_ficha_prazos_e_texto(): void
    {
        [, $espaco] = $this->entrar();
        $doc = $this->arquivar($espaco, 'Para imprimir', self::TEXTO);

        $this->get("/documentos/{$doc->id}/relatorio")
            ->assertOk()->assertSee('Para imprimir')->assertSee('10/12/2026')->assertSee('Texto integral');
    }

    public function test_reprocessar_nao_duplica_trechos_nem_prazos(): void
    {
        [, $espaco] = $this->entrar();
        $doc = $this->arquivar($espaco, 'x', self::TEXTO);
        $trechos = $doc->trechos()->count();
        $prazos = $doc->prazos()->count();

        $this->post("/documentos/{$doc->id}/reprocessar")->assertRedirect();

        $this->assertSame($trechos, $doc->trechos()->count());
        $this->assertSame($prazos, $doc->prazos()->count());
    }

    public function test_excluir_remove_documento_trechos_e_prazos(): void
    {
        [, $espaco] = $this->entrar();
        $doc = $this->arquivar($espaco, 'x', self::TEXTO);

        $this->delete("/documentos/{$doc->id}")->assertRedirect('/documentos');

        $this->assertDatabaseCount('documentos', 0);
        $this->assertDatabaseCount('trechos', 0);
        $this->assertDatabaseCount('prazos', 0);
    }
}
