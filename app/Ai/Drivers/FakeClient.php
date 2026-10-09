<?php

namespace App\Ai\Drivers;

use App\Ai\AiResponse;
use App\Ai\Contracts\AiClient;
use Illuminate\Support\Str;

/**
 * Driver offline e determinístico. Imita o comportamento de um LLM com ferramentas:
 *  1ª volta  -> pede a ferramenta search_documents com a pergunta do usuário;
 *  2ª volta  -> responde citando os trechos devolvidos pela ferramenta, no formato [T<id>].
 * A extração estruturada usa regex simples. Serve para dev, testes e demonstração sem chave.
 */
class FakeClient implements AiClient
{
    public function structured(string $system, string $user, array $schema): array
    {
        $tipo = 'outro';
        foreach (['contrato' => 'contrato', 'notificação' => 'notificacao', 'notificacao' => 'notificacao', 'petição' => 'peticao', 'peticao' => 'peticao'] as $palavra => $valor) {
            if (Str::contains(Str::lower($user), $palavra)) {
                $tipo = $valor;
                break;
            }
        }

        preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $user, $datas);
        $prazos = array_map(fn ($d) => ['descricao' => 'Data citada no documento', 'data' => $d], array_values(array_unique($datas[1])));

        preg_match_all('/R\$\s?([\d\.]+,\d{2})/', $user, $valores);
        $valores = array_map(fn ($v) => ['descricao' => 'Valor citado no documento', 'valor' => $v], array_values(array_unique($valores[1])));

        preg_match_all('/^(?:Contratante|Contratada|Autor|Réu|Reu|Notificante|Notificado):\s*(.+)$/mu', $user, $partes);

        return [
            'tipo' => $tipo,
            'resumo' => Str::limit(trim(preg_replace('/\s+/', ' ', $user)), 200),
            'partes' => array_values(array_map('trim', $partes[1])),
            'prazos' => $prazos,
            'valores' => $valores,
        ];
    }

    public function chat(string $system, array $messages, array $tools = []): AiResponse
    {
        $last = end($messages);
        $content = $last['content'] ?? '';

        // 2ª volta: já temos resultado de ferramenta.
        if (is_array($content) && ($content[0]['type'] ?? null) === 'tool_result') {
            return new AiResponse($this->answerFromToolResults($content));
        }

        $question = is_array($content) ? json_encode($content) : (string) $content;

        return new AiResponse(null, [[
            'id' => 'toolu_'.Str::random(8),
            'name' => 'search_documents',
            'input' => ['query' => $question, 'limit' => 3],
        ]]);
    }

    /** @param array<int, array<string, mixed>> $results */
    private function answerFromToolResults(array $results): string
    {
        $parts = [];
        foreach ($results as $result) {
            $payload = json_decode((string) ($result['content'] ?? ''), true);
            foreach ($payload['resultados'] ?? [] as $hit) {
                $parts[] = Str::limit($hit['texto'], 220).' [T'.$hit['trecho_id'].']';
            }
        }

        return $parts === []
            ? 'Não encontrei informações sobre isso nos documentos.'
            : "Com base nos documentos:\n- ".implode("\n- ", array_slice($parts, 0, 3));
    }
}
