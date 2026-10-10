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

        $frases = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+|\R+/u', $user) ?: [])));
        $frasePara = function (string $trecho) use ($frases): string {
            foreach ($frases as $frase) {
                if (str_contains($frase, $trecho)) {
                    return Str::limit($frase, 70);
                }
            }

            return 'Citado no documento';
        };

        preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $user, $datas);
        $prazos = array_map(fn ($d) => ['descricao' => $frasePara($d), 'data' => $d], array_values(array_unique($datas[1])));

        preg_match_all('/R\$\s?([\d\.]+,\d{2})/', $user, $valores);
        $valores = array_map(fn ($v) => ['descricao' => $frasePara($v), 'valor' => $v], array_values(array_unique($valores[1])));

        preg_match_all('/^(?:Contratante|Contratada|Autor|Réu|Reu|Notificante|Notificado):\s*(.+)$/mu', $user, $partes);
        $partes = array_values(array_map('trim', $partes[1]));

        return [
            'tipo' => $tipo,
            'resumo' => $this->resumo($tipo, $partes, count($prazos), count($valores), $frases),
            'partes' => $partes,
            'prazos' => $prazos,
            'valores' => $valores,
        ];
    }

    /** Resumo determinístico (o driver real pede isto ao LLM). */
    private function resumo(string $tipo, array $partes, int $prazos, int $valores, array $frases): string
    {
        if ($partes === [] && $prazos === 0 && $valores === 0) {
            return Str::limit($frases[0] ?? '', 200);
        }

        $nome = ['contrato' => 'Contrato', 'notificacao' => 'Notificação', 'peticao' => 'Petição'][$tipo] ?? 'Documento';
        $itens = [];
        if ($partes !== []) {
            $itens[] = count($partes).' '.(count($partes) === 1 ? 'parte identificada' : 'partes identificadas').' ('.implode(', ', $partes).')';
        }
        if ($prazos > 0) {
            $itens[] = $prazos.' '.($prazos === 1 ? 'data' : 'datas');
        }
        if ($valores > 0) {
            $itens[] = $valores.' '.($valores === 1 ? 'valor' : 'valores');
        }

        return $nome.' com '.implode(', ', $itens).'. (Resumo automático do modo de demonstração.)';
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
