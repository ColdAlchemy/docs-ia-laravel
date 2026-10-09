@extends('layout')
@section('titulo', $documento->titulo)
@section('conteudo')
<div class="card">
    <h2>{{ $documento->titulo }}</h2>
    <p><span class="etiqueta">{{ $documento->status }}</span> · {{ $documento->trechos_count }} trecho(s) indexado(s)</p>
    @if ($documento->erro) <p class="erro">{{ $documento->erro }}</p> @endif
    <div style="display:flex;gap:8px">
        <form method="POST" action="{{ route('documentos.reprocessar', $documento) }}">@csrf<button type="submit">Reprocessar</button></form>
        <form method="POST" action="{{ route('documentos.destroy', $documento) }}">@csrf @method('DELETE')<button class="perigo" type="submit">Excluir</button></form>
    </div>
</div>

@if ($documento->dados)
<div class="card">
    <h3>Dados extraídos pela IA</h3>
    <p><strong>Tipo:</strong> {{ $documento->dados['tipo'] ?? '-' }}</p>
    <p><strong>Resumo:</strong> {{ $documento->dados['resumo'] ?? '-' }}</p>
    <p><strong>Partes:</strong> {{ implode(', ', $documento->dados['partes'] ?? []) ?: '-' }}</p>
    <h4>Prazos</h4>
    <ul>@forelse ($documento->dados['prazos'] ?? [] as $p)<li>{{ $p['data'] }} — {{ $p['descricao'] }}</li>@empty<li class="mut">Nenhum</li>@endforelse</ul>
    <h4>Valores</h4>
    <ul>@forelse ($documento->dados['valores'] ?? [] as $v)<li>R$ {{ $v['valor'] }} — {{ $v['descricao'] }}</li>@empty<li class="mut">Nenhum</li>@endforelse</ul>
</div>
@endif

<div class="card">
    <h3>Texto</h3>
    <div style="white-space:pre-wrap">{{ $documento->conteudo }}</div>
</div>
@endsection
