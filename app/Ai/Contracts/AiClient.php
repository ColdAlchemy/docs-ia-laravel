<?php

namespace App\Ai\Contracts;

use App\Ai\AiResponse;

/**
 * Contrato único para falar com um LLM. A aplicação depende desta interface,
 * não de um provedor: o container injeta o driver configurado em config/ia.php.
 */
interface AiClient
{
    /**
     * Saída estruturada: devolve um array que obedece ao JSON Schema informado.
     *
     * @param  array<string, mixed>  $schema  JSON Schema (type: object)
     * @return array<string, mixed>
     */
    public function structured(string $system, string $user, array $schema): array;

    /**
     * Uma volta de conversa, com suporte a ferramentas (tool calling).
     * Mensagens no formato Anthropic: ['role' => 'user|assistant', 'content' => string|array de blocos].
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools  [{name, description, input_schema}]
     */
    public function chat(string $system, array $messages, array $tools = []): AiResponse;
}
