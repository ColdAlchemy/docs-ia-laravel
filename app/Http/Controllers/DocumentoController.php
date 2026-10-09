<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentoRequest;
use App\Jobs\ProcessarDocumento;
use App\Models\Documento;

class DocumentoController extends Controller
{
    public function index()
    {
        return view('documentos.index', [
            'documentos' => Documento::withCount('trechos')->latest()->paginate(10),
        ]);
    }

    public function create()
    {
        return view('documentos.create');
    }

    public function store(StoreDocumentoRequest $request)
    {
        $documento = Documento::create([
            'titulo' => $request->validated('titulo'),
            'conteudo' => $request->texto(),
        ]);

        ProcessarDocumento::dispatch($documento);

        return redirect()->route('documentos.show', $documento)->with('ok', 'Documento enviado para processamento.');
    }

    public function show(Documento $documento)
    {
        $documento->loadCount('trechos');

        return view('documentos.show', compact('documento'));
    }

    public function reprocessar(Documento $documento)
    {
        ProcessarDocumento::dispatch($documento);

        return back()->with('ok', 'Reprocessamento iniciado.');
    }

    public function destroy(Documento $documento)
    {
        $documento->delete();

        return redirect()->route('documentos.index')->with('ok', 'Documento removido.');
    }
}
