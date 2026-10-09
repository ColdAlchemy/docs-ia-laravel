<?php

namespace App\Providers;

use App\Ai\Contracts\AiClient;
use App\Ai\Contracts\Embedder;
use App\Ai\Drivers\AnthropicClient;
use App\Ai\Drivers\FakeClient;
use App\Ai\HashingEmbedder;
use App\Services\Chunker;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // O driver de IA é escolhido por configuração (AI_DRIVER) e injetado onde for pedido AiClient.
        $this->app->singleton(AiClient::class, fn ($app) => match (config('ia.driver')) {
            'anthropic' => new AnthropicClient($app->make(Http::class), config('ia.anthropic')),
            default => new FakeClient,
        });

        $this->app->singleton(Embedder::class, fn () => new HashingEmbedder(config('ia.embedding_dims')));

        $this->app->bind(Chunker::class, fn () => new Chunker(config('ia.chunk_size')));
    }

    public function boot(): void
    {
        //
    }
}
