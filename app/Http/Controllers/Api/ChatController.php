<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversa;
use App\Models\Documento;
use App\Services\Consultas;
use App\Services\Escopo;
use App\Services\VectorSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoints JSON usados pelo leitor e pela tela de consultas (sessão + CSRF, sempre dentro de um espaço). */
class ChatController extends Controller
{
    public function perguntar(Request $request, Consultas $consultas): JsonResponse
    {
        $dados = $request->validate([
            'pergunta' => ['required', 'string', 'min:3', 'max:1000'],
            'conversa_id' => ['nullable', 'integer'],
            'documento_id' => ['nullable', 'integer'],
        ], [
            'pergunta.required' => 'Escreva a pergunta.',
            'pergunta.min' => 'A pergunta precisa ter pelo menos 3 caracteres.',
            'pergunta.max' => 'A pergunta pode ter até 1000 caracteres.',
        ]);

        $conversa = null;
        $documento = null;

        if (! empty($dados['conversa_id'])) {
            // A conversa precisa ser do usuário e do espaço ativo.
            $conversa = Conversa::where('espaco_id', $this->espaco()->id)
                ->where('user_id', $request->user()->id)
                ->findOrFail($dados['conversa_id']);
        } elseif (! empty($dados['documento_id'])) {
            $documento = $this->doDocumentoDoEspaco(Documento::findOrFail($dados['documento_id']));
        }

        $resposta = $consultas->perguntar($request->user(), $this->espaco()->id, $dados['pergunta'], $conversa, $documento);

        return response()->json([
            'conversa_id' => $resposta->conversa_id,
            'mensagem' => Consultas::paraTela($resposta),
        ]);
    }

    public function busca(Request $request, VectorSearch $search): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'min:2', 'max:300']])['q'];

        return response()->json([
            'consulta' => $q,
            'resultados' => $search->search($q, new Escopo($this->espaco()->id)),
        ]);
    }
}
