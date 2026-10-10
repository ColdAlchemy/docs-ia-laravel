<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descobre o espaço de trabalho ativo do usuário (guardado na sessão) e o disponibiliza
 * para os controladores e as views. Só aceita espaços de que o usuário participa.
 */
class EspacoAtual
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $espacos = $user->espacos()->get();

        $espaco = $espacos->firstWhere('id', (int) $request->session()->get('espaco_id')) ?? $espacos->first();

        if (! $espaco) {
            // Conta sem nenhum espaço (ex.: removida de todos): cria um espaço pessoal.
            $espaco = $user->espacos()->create(['nome' => 'Espaço de '.$user->name, 'dono_id' => $user->id]);
            $espacos = $user->espacos()->get();
        }

        $request->session()->put('espaco_id', $espaco->id);
        $request->attributes->set('espaco', $espaco);
        View::share(['espacoAtual' => $espaco, 'meusEspacos' => $espacos]);

        return $next($request);
    }
}
