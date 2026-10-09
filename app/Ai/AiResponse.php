<?php

namespace App\Ai;

/**
 * Resposta de uma volta de chat: texto final e/ou chamadas de ferramenta.
 */
final class AiResponse
{
    /**
     * @param  array<int, array{id: string, name: string, input: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public readonly ?string $text = null,
        public readonly array $toolCalls = [],
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }

    /** Blocos de conteúdo do assistente, para devolver ao histórico da conversa. */
    public function toBlocks(): array
    {
        $blocks = [];
        if ($this->text !== null && $this->text !== '') {
            $blocks[] = ['type' => 'text', 'text' => $this->text];
        }
        foreach ($this->toolCalls as $call) {
            $blocks[] = ['type' => 'tool_use', 'id' => $call['id'], 'name' => $call['name'], 'input' => $call['input']];
        }

        return $blocks;
    }
}
