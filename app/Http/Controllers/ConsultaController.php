<?php

namespace App\Http\Controllers;

use App\Models\Conversa;
use App\Services\Consultas;
use Illuminate\Http\Request;

class ConsultaController extends Controller
{
    /** Histórico de conversas do usuário neste espaço; mostra a conversa pedida ou o formulário em branco. */
    public function index(Request $request, ?Conversa $conversa = null)
    {
        $minhas = Conversa::where('espaco_id', $this->espaco()->id)
            ->where('user_id', $request->user()->id)
            ->with('documento:id,titulo,numero,created_at')
            ->latest('updated_at')
            ->get();

        if ($conversa) {
            abort_unless($minhas->contains('id', $conversa->id), 404);
            $conversa->load('mensagens');
        }

        return view('consultas.index', [
            'conversas' => $minhas,
            'conversa' => $conversa,
            'mensagens' => $conversa ? $conversa->mensagens->map(fn ($m) => Consultas::paraTela($m))->all() : [],
        ]);
    }

    public function destroy(Request $request, Conversa $conversa)
    {
        abort_unless(
            $conversa->espaco_id === $this->espaco()->id && $conversa->user_id === $request->user()->id,
            404,
        );
        $conversa->delete();

        return redirect()->route('consultas')->with('ok', 'Conversa apagada.');
    }
}
