<?php

namespace App\Services;

/**
 * Limita o que o agente enxerga: sempre um espaço de trabalho e, opcionalmente, um único documento.
 * É a barreira que impede uma pergunta de recuperar trechos de outro espaço.
 */
final class Escopo
{
    public function __construct(
        public readonly int $espacoId,
        public readonly ?int $documentoId = null,
    ) {}
}
