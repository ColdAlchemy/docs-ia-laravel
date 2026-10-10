<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Espaco;

abstract class Controller
{
    /** Espaço de trabalho ativo, definido pelo middleware EspacoAtual. */
    protected function espaco(): Espaco
    {
        return request()->attributes->get('espaco');
    }

    /** Garante que o documento pertence ao espaço ativo (senão, 404: não revela que existe). */
    protected function doDocumentoDoEspaco(Documento $documento): Documento
    {
        abort_unless($documento->espaco_id === $this->espaco()->id, 404);

        return $documento;
    }
}
