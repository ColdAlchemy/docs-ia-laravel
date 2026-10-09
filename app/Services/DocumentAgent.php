<?php

namespace App\Services;

use App\Ai\Contracts\AiClient;

/**
 * Agente de perguntas e respostas sobre os documentos.
 * Laço de tool calling: o LLM decide chamar ferramentas; executamos e devolvemos
 * o resultado até ele produzir a resposta final (limitado por ia.agent_max_steps).
 */
class DocumentAgent
{
    private const SYSTEM = 'Você é um assistente que responde SOMENTE com base nos documentos do usuário. '
        .'Use as ferramentas para buscar informação. Cite os trechos usados no formato [T<id>]. '
        .'Se não houver informação suficiente, diga que não encontrou. Não dê aconselhamento jurídico.';

    public function __construct(
        private readonly AiClient $ai,
        private readonly AgentTools $tools,
        private readonly AnswerEvaluator $evaluator,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history  mensagens anteriores (texto)
     * @return array{resposta: string, fontes: array<int, array<string, mixed>>, passos: array<int, array<string, mixed>>, avaliacao: array<string, mixed>}
     */
    public function ask(string $question, array $history = []): array
    {
        $messages = [...$history, ['role' => 'user', 'content' => $question]];
        $steps = [];
        $sources = [];
        $answer = '';

        for ($i = 0; $i < config('ia.agent_max_steps'); $i++) {
            $response = $this->ai->chat(self::SYSTEM, $messages, $this->tools->definitions());

            if (! $response->wantsTools()) {
                $answer = $response->text ?? '';
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->toBlocks()];

            $results = [];
            foreach ($response->toolCalls as $call) {
                $output = $this->tools->run($call['name'], $call['input']);
                $steps[] = ['ferramenta' => $call['name'], 'entrada' => $call['input']];

                foreach ($output['resultados'] ?? [] as $hit) {
                    $sources[$hit['trecho_id']] = $hit;
                }

                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $call['id'],
                    'content' => json_encode($output, JSON_UNESCAPED_UNICODE),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $results];
        }

        if ($answer === '') {
            $answer = 'Não consegui concluir a resposta dentro do limite de passos.';
        }

        return [
            'resposta' => $answer,
            'fontes' => array_values($sources),
            'passos' => $steps,
            'avaliacao' => $this->evaluator->evaluate($answer, array_keys($sources)),
        ];
    }
}
