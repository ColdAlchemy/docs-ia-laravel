<?php

namespace App\Http\Controllers;

use App\Models\Espaco;
use App\Models\User;
use Illuminate\Http\Request;

class EspacoController extends Controller
{
    public function index(Request $request)
    {
        return view('espacos.index', [
            'espacos' => $request->user()->espacos()->with('membros:id,name,email')->withCount('documentos')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate(
            ['nome' => ['required', 'string', 'max:80']],
            ['nome.required' => 'Dê um nome ao espaço.'],
        );

        $espaco = $request->user()->espacos()->create($dados + ['dono_id' => $request->user()->id]);
        $request->session()->put('espaco_id', $espaco->id);

        return redirect()->route('documentos.index')->with('ok', "Espaço “{$espaco->nome}” criado e aberto.");
    }

    public function trocar(Request $request, Espaco $espaco)
    {
        abort_unless($request->user()->participaDe($espaco->id), 404);
        $request->session()->put('espaco_id', $espaco->id);

        return redirect()->route('documentos.index');
    }

    public function adicionarMembro(Request $request, Espaco $espaco)
    {
        $this->somenteDono($request, $espaco);

        $dados = $request->validate(
            ['email' => ['required', 'email']],
            ['email.required' => 'Informe o e-mail do membro.', 'email.email' => 'Esse e-mail não parece válido.'],
        );

        $novo = User::where('email', $dados['email'])->first();
        if (! $novo) {
            return back()->withErrors(['email' => 'Não há conta com esse e-mail. A pessoa precisa se cadastrar antes.'])->withInput();
        }
        if ($espaco->membros()->where('users.id', $novo->id)->exists()) {
            return back()->withErrors(['email' => 'Essa pessoa já participa do espaço.'])->withInput();
        }

        $espaco->membros()->attach($novo->id);

        return back()->with('ok', "{$novo->name} agora participa de “{$espaco->nome}”.");
    }

    public function removerMembro(Request $request, Espaco $espaco, User $usuario)
    {
        $this->somenteDono($request, $espaco);
        abort_if($usuario->id === $espaco->dono_id, 422, 'O dono não pode sair do próprio espaço.');

        $espaco->membros()->detach($usuario->id);

        return back()->with('ok', "{$usuario->name} foi removido(a) do espaço.");
    }

    private function somenteDono(Request $request, Espaco $espaco): void
    {
        abort_unless($request->user()->participaDe($espaco->id), 404);
        abort_unless($espaco->dono_id === $request->user()->id, 403, 'Só o dono do espaço gerencia os membros.');
    }
}
