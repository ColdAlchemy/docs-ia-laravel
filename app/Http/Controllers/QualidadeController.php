<?php

namespace App\Http\Controllers;

use App\Models\PerguntaVerificacao;
use App\Services\Qualidade;
use Illuminate\Http\Request;

class QualidadeController extends Controller
{
    public function index(Qualidade $qualidade)
    {
        $espaco = $this->espaco();

        return view('qualidade.index', [
            'ind' => $qualidade->indicadores($espaco),
            'semanas' => $qualidade->porSemana($espaco),
            'reprovadas' => $qualidade->reprovadas($espaco),
            'verificacao' => $espaco->perguntasVerificacao()->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate(
            ['pergunta' => ['required', 'string', 'min:5', 'max:300']],
            ['pergunta.required' => 'Escreva a pergunta de verificação.', 'pergunta.min' => 'A pergunta precisa ter pelo menos 5 caracteres.'],
        );

        $this->espaco()->perguntasVerificacao()->create($dados);

        return redirect()->route('qualidade')->with('ok', 'Pergunta adicionada ao conjunto de verificação.');
    }

    public function destroy(PerguntaVerificacao $pergunta)
    {
        abort_unless($pergunta->espaco_id === $this->espaco()->id, 404);
        $pergunta->delete();

        return redirect()->route('qualidade')->with('ok', 'Pergunta removida do conjunto.');
    }

    public function executar(Qualidade $qualidade)
    {
        $n = $qualidade->executarLote($this->espaco());

        return redirect()->route('qualidade')->with('ok', $n
            ? "Verificação concluída: {$n} pergunta(s) executada(s)."
            : 'Adicione ao menos uma pergunta de verificação para executar.');
    }
}
