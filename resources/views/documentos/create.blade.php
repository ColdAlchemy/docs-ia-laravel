@extends('layout')
@section('titulo', 'Arquivar documento')
@section('erros-no-campo', '1')
@section('conteudo')
<div class="topo-pagina">
    <div>
        <h1>Arquivar documento</h1>
        <p>Entra no espaço <strong>{{ $espacoAtual->nome }}</strong> com o próximo número de registro.</p>
    </div>
</div>

<form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data" novalidate style="max-width:46rem" data-proteger-saida>
    @csrf
    <div class="campo">
        <label for="titulo">Título</label>
        <input id="titulo" name="titulo" type="text" value="{{ old('titulo') }}" maxlength="150" autocomplete="off" required
               @error('titulo') aria-invalid="true" aria-describedby="titulo-erro" @enderror>
        @error('titulo') <p class="erro-campo" id="titulo-erro">{{ $message }}</p> @enderror
    </div>

    <div class="campo">
        <label for="conteudo">Texto do documento</label>
        <textarea id="conteudo" name="conteudo" autocomplete="off" aria-describedby="conteudo-dica @error('conteudo') conteudo-erro @enderror"
                  @error('conteudo') aria-invalid="true" @enderror>{{ old('conteudo') }}</textarea>
        <p class="dica" id="conteudo-dica">Cole o texto aqui ou envie um arquivo abaixo.</p>
        @error('conteudo') <p class="erro-campo" id="conteudo-erro">{{ $message }}</p> @enderror
    </div>

    <div class="campo">
        <label for="arquivo">Ou envie um arquivo (.txt, .md ou .pdf, até 5&nbsp;MB)</label>
        <input id="arquivo" name="arquivo" type="file" accept=".txt,.md,.pdf,application/pdf,text/plain,text/markdown"
               aria-describedby="arquivo-dica @error('arquivo') arquivo-erro @enderror" @error('arquivo') aria-invalid="true" @enderror>
        <p class="dica" id="arquivo-dica">PDFs precisam ter texto selecionável. Imagens e digitalizações não são lidas (sem OCR).</p>
        @error('arquivo') <p class="erro-campo" id="arquivo-erro">{{ $message }}</p> @enderror
    </div>

    <div class="topo-acoes">
        <button type="submit" class="botao">Arquivar e processar</button>
        <a href="{{ route('documentos.index') }}">Cancelar</a>
    </div>
</form>
@endsection
