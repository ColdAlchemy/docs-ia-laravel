<?php

namespace Tests\Feature;

use App\Ai\Contracts\AiClient;
use App\Ai\Drivers\AnthropicClient;
use App\Services\AgentTools;
use App\Services\DocumentAgent;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http as HttpFacade;
use RuntimeException;
use Tests\TestCase;

/** Testa o driver real com a API simulada (Http::fake): nenhuma chamada de rede. */
class AnthropicDriverTest extends TestCase
{
    use RefreshDatabase;

    private function client(): AnthropicClient
    {
        return new AnthropicClient(app(Http::class), [
            'key' => 'chave-de-teste', 'model' => 'modelo-teste', 'url' => 'https://api.test/v1/messages',
            'version' => '2023-06-01', 'max_tokens' => 100, 'timeout' => 5,
        ]);
    }

    public function test_structured_forca_a_ferramenta_e_devolve_o_input(): void
    {
        HttpFacade::fake(['api.test/*' => HttpFacade::response([
            'content' => [['type' => 'tool_use', 'id' => 't1', 'name' => 'registrar', 'input' => ['tipo' => 'contrato']]],
        ])]);

        $out = $this->client()->structured('sys', 'texto', ['type' => 'object', 'properties' => []]);

        $this->assertSame(['tipo' => 'contrato'], $out);
        HttpFacade::assertSent(fn ($req) => $req->header('x-api-key')[0] === 'chave-de-teste'
            && $req['tool_choice'] === ['type' => 'tool', 'name' => 'registrar']
            && $req['model'] === 'modelo-teste');
    }

    public function test_chat_interpreta_texto_e_chamadas_de_ferramenta(): void
    {
        HttpFacade::fake(['api.test/*' => HttpFacade::response([
            'content' => [
                ['type' => 'text', 'text' => 'Vou buscar.'],
                ['type' => 'tool_use', 'id' => 'abc', 'name' => 'search_documents', 'input' => ['query' => 'prazo']],
            ],
        ])]);

        $r = $this->client()->chat('sys', [['role' => 'user', 'content' => 'oi']]);

        $this->assertTrue($r->wantsTools());
        $this->assertSame('search_documents', $r->toolCalls[0]['name']);
        $this->assertSame('Vou buscar.', $r->text);
    }

    public function test_erro_http_vira_excecao_clara(): void
    {
        HttpFacade::fake(['api.test/*' => HttpFacade::response(['error' => 'x'], 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falha na API da IA (500)');

        $this->client()->chat('sys', [['role' => 'user', 'content' => 'oi']]);
    }

    public function test_sem_chave_nao_chama_a_rede(): void
    {
        HttpFacade::fake();
        $client = new AnthropicClient(app(Http::class), ['key' => null] + config('ia.anthropic'));

        try {
            $client->chat('sys', [['role' => 'user', 'content' => 'oi']]);
            $this->fail('Deveria lançar exceção.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ANTHROPIC_API_KEY', $e->getMessage());
        }

        HttpFacade::assertNothingSent();
    }

    public function test_agente_executa_o_laco_de_tool_calling_com_o_driver_real(): void
    {
        // 1ª resposta pede a ferramenta list_prazos; 2ª responde com texto.
        HttpFacade::fakeSequence('api.test/*')
            ->push(['content' => [['type' => 'tool_use', 'id' => 'u1', 'name' => 'list_prazos', 'input' => new \stdClass]]])
            ->push(['content' => [['type' => 'text', 'text' => 'Não há prazos cadastrados.']]]);

        $this->app->instance(AiClient::class, $this->client());

        $r = app(DocumentAgent::class)->ask('Quais os prazos?');

        $this->assertSame('list_prazos', $r['passos'][0]['ferramenta']);
        $this->assertSame('Não há prazos cadastrados.', $r['resposta']);
        HttpFacade::assertSentCount(2);
        // A 2ª requisição deve devolver o tool_result ao modelo.
        HttpFacade::assertSent(fn ($req) => collect($req['messages'])->contains(
            fn ($m) => is_array($m['content']) && ($m['content'][0]['type'] ?? null) === 'tool_result'
        ));
    }

    public function test_ferramenta_desconhecida_devolve_erro_sem_quebrar(): void
    {
        $this->assertArrayHasKey('erro', app(AgentTools::class)->run('nao_existe', []));
    }
}
