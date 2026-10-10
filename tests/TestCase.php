<?php

namespace Tests;

use App\Jobs\ProcessarDocumento;
use App\Models\Documento;
use App\Models\Espaco;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Cria um usuário com um espaço de trabalho (onde ele é dono) e já o autentica. */
    protected function entrar(?User $user = null): array
    {
        $user ??= User::factory()->create();
        $espaco = Espaco::factory()->create(['dono_id' => $user->id]);
        $this->actingAs($user)->withSession(['espaco_id' => $espaco->id]);

        return [$user, $espaco];
    }

    /** Cria e processa (fila síncrona) um documento no espaço. */
    protected function arquivar(Espaco $espaco, string $titulo, string $texto): Documento
    {
        $doc = Documento::create([
            'espaco_id' => $espaco->id,
            'numero' => $espaco->proximoNumero(),
            'titulo' => $titulo,
            'conteudo' => $texto,
        ]);
        ProcessarDocumento::dispatchSync($doc);

        return $doc->refresh();
    }
}
