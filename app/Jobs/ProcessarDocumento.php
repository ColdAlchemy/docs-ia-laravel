<?php

namespace App\Jobs;

use App\Models\Documento;
use App\Services\DocumentProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Processa o documento em segundo plano (fila). Em testes a fila é "sync".
 */
class ProcessarDocumento implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly Documento $documento) {}

    public function handle(DocumentProcessor $processor): void
    {
        $processor->process($this->documento);
    }

    public function failed(Throwable $e): void
    {
        $this->documento->update([
            'status' => Documento::STATUS_ERRO,
            'erro' => $e->getMessage(),
        ]);
    }
}
