<?php

namespace App\Ai\Drivers;

use App\Ai\AiResponse;
use App\Ai\Contracts\AiClient;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * Driver real: Messages API da Anthropic.
 * - chat(): suporta tool calling (blocos tool_use / tool_result).
 * - structured(): força uma ferramenta cujo input_schema é o schema desejado
 *   (técnica de "saída estruturada via tool use").
 */
class AnthropicClient implements AiClient
{
    public function __construct(
        private readonly Http $http,
        private readonly array $config,
    ) {}

    public function structured(string $system, string $user, array $schema): array
    {
        $tool = [
            'name' => 'registrar',
            'description' => 'Registra os dados extraídos do documento.',
            'input_schema' => $schema,
        ];

        $data = $this->request([
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
            'tools' => [$tool],
            'tool_choice' => ['type' => 'tool', 'name' => 'registrar'],
        ]);

        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use') {
                return $block['input'];
            }
        }

        throw new RuntimeException('A IA não devolveu a saída estruturada esperada.');
    }

    public function chat(string $system, array $messages, array $tools = []): AiResponse
    {
        $payload = ['system' => $system, 'messages' => $messages];
        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $data = $this->request($payload);

        $text = '';
        $calls = [];
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $calls[] = ['id' => $block['id'], 'name' => $block['name'], 'input' => $block['input'] ?? []];
            }
        }

        return new AiResponse($text !== '' ? $text : null, $calls);
    }

    /** @return array<string, mixed> */
    private function request(array $payload): array
    {
        if (empty($this->config['key'])) {
            throw new RuntimeException('ANTHROPIC_API_KEY não configurada.');
        }

        $response = $this->http
            ->withHeaders([
                'x-api-key' => $this->config['key'],
                'anthropic-version' => $this->config['version'],
            ])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->post($this->config['url'], $payload + [
                'model' => $this->config['model'],
                'max_tokens' => $this->config['max_tokens'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Falha na API da IA ('.$response->status().'): '.$response->body());
        }

        return $response->json();
    }
}
