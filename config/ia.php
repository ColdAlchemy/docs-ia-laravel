<?php

return [
    /*
     * Driver de IA: "fake" (offline, determinístico; padrão em dev e testes)
     * ou "anthropic" (chama a API da Anthropic; exige ANTHROPIC_API_KEY).
     */
    'driver' => env('AI_DRIVER', 'fake'),

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1/messages'),
        'version' => '2023-06-01',
        'max_tokens' => 1024,
        'timeout' => 60,
    ],

    // Dimensões do embedder local por hashing (sem dependência externa).
    'embedding_dims' => 256,

    // Tamanho máximo (caracteres) de cada trecho indexado.
    'chunk_size' => 240,

    // Busca semântica.
    'search' => [
        'limit' => 5,
        'min_score' => 0.08,
    ],

    // Máximo de voltas do agente (chamadas de ferramenta) por pergunta.
    'agent_max_steps' => 4,
];
