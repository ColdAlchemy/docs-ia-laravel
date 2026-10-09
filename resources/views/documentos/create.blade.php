@extends('layout')
@section('titulo', 'Novo documento')
@section('conteudo')
<div class="card">
    <h2>Novo documento</h2>
    <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data">
        @csrf
        <label for="titulo">Título</label>
        <input id="titulo" type="text" name="titulo" value="{{ old('titulo') }}" required>
        <label for="conteudo">Texto do documento</label>
        <textarea id="conteudo" name="conteudo">{{ old('conteudo') }}</textarea>
        <label for="arquivo">Ou envie um arquivo (.txt, .md)</label>
        <input id="arquivo" type="file" name="arquivo" accept=".txt,.md">
        <p><button type="submit">Enviar e processar</button></p>
    </form>
</div>
@endsection
