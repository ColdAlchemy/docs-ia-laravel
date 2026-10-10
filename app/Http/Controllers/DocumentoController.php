<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentoRequest;
use App\Jobs\ProcessarDocumento;
use App\Models\Documento;
use App\Services\Consultas;
use App\Services\Leitura;
use App\Services\PdfTexto;
use Illuminate\Http\Request;
use RuntimeException;

class DocumentoController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'in:contrato,notificacao,peticao,outro'],
            'status' => ['nullable', 'in:pendente,processando,pronto,erro'],
        ]);

        $documentos = $this->espaco()->documentos()
            ->withCount('trechos')
            ->withMin(['prazos as proximo_prazo' => fn ($q) => $q->where('data', '>=', now()->toDateString())], 'data')
            ->when($filtros['q'] ?? null, fn ($q, $busca) => $q->where('titulo', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $busca).'%'))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('dados->tipo', $tipo))
            ->when($filtros['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('numero')
            ->paginate(12)
            ->withQueryString();

        return view('documentos.index', [
            'documentos' => $documentos,
            'filtros' => $filtros,
            'totalNoEspaco' => $this->espaco()->documentos()->count(),
        ]);
    }

    public function create()
    {
        return view('documentos.create');
    }

    public function store(StoreDocumentoRequest $request, PdfTexto $pdf)
    {
        $origem = 'texto';
        $nomeArquivo = null;

        if ($request->hasFile('arquivo')) {
            $arquivo = $request->file('arquivo');
            $nomeArquivo = $arquivo->getClientOriginalName();
            $origem = $request->enviouPdf() ? 'pdf' : 'arquivo';

            try {
                $texto = $request->enviouPdf()
                    ? $pdf->extrair($arquivo->getRealPath())
                    : (string) file_get_contents($arquivo->getRealPath());
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['arquivo' => $e->getMessage()]);
            }
        } else {
            $texto = (string) $request->input('conteudo');
        }

        $documento = Documento::create([
            'espaco_id' => $this->espaco()->id,
            'user_id' => $request->user()->id,
            'numero' => $this->espaco()->proximoNumero(),
            'origem' => $origem,
            'arquivo_nome' => $nomeArquivo,
            'titulo' => $request->validated('titulo'),
            'conteudo' => $texto,
        ]);

        ProcessarDocumento::dispatch($documento);

        return redirect()->route('documentos.show', $documento)->with('ok', 'Documento recebido. Os dados aparecem assim que o processamento terminar.');
    }

    public function show(Request $request, Documento $documento, Leitura $leitura)
    {
        $this->doDocumentoDoEspaco($documento);
        $documento->loadCount('trechos')->load(['prazos', 'autor:id,name']);

        $conversa = $documento->conversas()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')->first();

        return view('documentos.show', [
            'documento' => $documento,
            'blocos' => $leitura->blocos($documento),
            'conversa' => $conversa,
            'mensagens' => $conversa ? $conversa->mensagens->map(fn ($m) => Consultas::paraTela($m))->all() : [],
            // Trecho a destacar ao chegar de uma citação (?trecho=ID).
            'destaque' => (int) $request->query('trecho'),
        ]);
    }

    public function relatorio(Request $request, Documento $documento)
    {
        $this->doDocumentoDoEspaco($documento);
        $documento->load(['prazos', 'autor:id,name']);

        $conversas = $documento->conversas()
            ->where('user_id', $request->user()->id)
            ->with('mensagens')
            ->orderBy('created_at')->get();

        return view('documentos.relatorio', compact('documento', 'conversas'));
    }

    public function reprocessar(Documento $documento)
    {
        $this->doDocumentoDoEspaco($documento);

        ProcessarDocumento::dispatch($documento);

        return back()->with('ok', 'Reprocessamento iniciado.');
    }

    public function destroy(Documento $documento)
    {
        $this->doDocumentoDoEspaco($documento)->delete();

        return redirect()->route('documentos.index')->with('ok', 'Documento removido do arquivo.');
    }
}
