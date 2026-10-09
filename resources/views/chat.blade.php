@extends('layout')
@section('titulo', 'Perguntar à IA')
@section('conteudo')
<div class="card">
    <h2>Perguntar à IA</h2>
    <p class="mut">O assistente responde somente com base nos documentos processados e cita os trechos usados.</p>
    <div id="chat-app" data-endpoint="{{ route('api.chat') }}"></div>
</div>
@endsection
