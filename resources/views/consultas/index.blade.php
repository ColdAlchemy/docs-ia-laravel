@extends('layout')
@section('titulo', 'Consultas')
@section('conteudo')
@php
    $config = [
        'modo' => 'consulta',
        'endpoint' => route('api.perguntar'),
        'documentoId' => null,
        'conversaId' => $conversa?->id,
        'mensagens' => $mensagens,
        'pronto' => true,
        'urlDocumento' => url('/documentos/__ID__').'?trecho=__TRECHO__',
        'sugestoes' => ['Quais são os prazos mais próximos?', 'Quanto é a multa por atraso?', 'Quais documentos citam rescisão?'],
    ];
@endphp
<div class="topo-pagina">
    <div>
        <h1>Consultas</h1>
        <p>Perguntas sobre todos os documentos de <strong>{{ $espacoAtual->nome }}</strong>.</p>
    </div>
    <div class="topo-acoes">
        <a class="botao-contorno" href="{{ route('consultas') }}">Nova consulta</a>
    </div>
</div>

<div class="consultas">
    <nav aria-label="Histórico de conversas">
        @if ($conversas->isEmpty())
            <p class="mut pequeno">Suas conversas aparecem aqui.</p>
        @else
            <ul class="historico">
                @foreach ($conversas as $c)
                    <li>
                        <a href="{{ route('consultas', $c) }}" @if ($conversa && $conversa->id === $c->id) aria-current="page" @endif>
                            <span class="t">{{ $c->titulo }}</span>
                            <span class="m">{{ $c->updated_at->format('d/m/Y H:i') }}@if ($c->documento) · {{ $c->documento->registro() }}@endif</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </nav>

    <section class="parecer-folha" aria-label="Conversa">
        @if ($conversa)
            <div class="topo-acoes acoes-tela" style="justify-content:space-between;margin-bottom:.5rem">
                <p class="legenda" style="margin:0">
                    Conversa de {{ $conversa->created_at->format('d/m/Y') }}
                    @if ($conversa->documento) sobre <a href="{{ route('documentos.show', $conversa->documento) }}">{{ $conversa->documento->titulo }}</a>@endif
                </p>
                <form method="POST" action="{{ route('consultas.destroy', $conversa) }}" data-confirmar="Esta conversa e as respostas dela serão apagadas. Isso não pode ser desfeito.">
                    @csrf @method('DELETE')
                    <button type="submit" class="botao-link">Apagar conversa…</button>
                </form>
            </div>
        @endif
        <div id="perguntas-app" data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) }}"></div>
    </section>
</div>
@endsection
