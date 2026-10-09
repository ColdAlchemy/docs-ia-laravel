<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentAgent;
use App\Services\VectorSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function chat(Request $request, DocumentAgent $agent): JsonResponse
    {
        $dados = $request->validate([
            'pergunta' => ['required', 'string', 'min:3', 'max:1000'],
            'historico' => ['nullable', 'array', 'max:10'],
            'historico.*.role' => ['required_with:historico', 'in:user,assistant'],
            'historico.*.content' => ['required_with:historico', 'string', 'max:4000'],
        ]);

        return response()->json($agent->ask($dados['pergunta'], $dados['historico'] ?? []));
    }

    public function busca(Request $request, VectorSearch $search): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string', 'min:2', 'max:300']])['q'];

        return response()->json(['consulta' => $q, 'resultados' => $search->search($q)]);
    }
}
