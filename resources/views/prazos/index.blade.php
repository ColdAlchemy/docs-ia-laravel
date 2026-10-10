@extends('layout')
@section('titulo', 'Prazos')
@section('conteudo')
@php
    $quando = function ($dias) {
        if ($dias === 0) return 'vence hoje';
        if ($dias === 1) return 'vence amanhã';
        if ($dias > 0) return "em {$dias} dias";
        if ($dias === -1) return 'venceu ontem';
        return 'venceu há '.abs($dias).' dias';
    };
    $dias = fn ($p) => (int) now()->startOfDay()->diffInDays($p->data->startOfDay(), false);
    $semana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
@endphp
<div class="topo-pagina">
    <div>
        <h1>Prazos</h1>
        <p>Datas extraídas dos documentos de <strong>{{ $espacoAtual->nome }}</strong>.</p>
    </div>
    <div class="topo-acoes">
        <a class="botao-contorno" href="{{ route('prazos.ics') }}" download>Exportar agenda (.ics)</a>
    </div>
</div>

<div class="mes-nav" role="group" aria-label="Navegar entre meses">
    <a class="botao-contorno" href="{{ route('prazos', ['mes' => $anterior]) }}" ><span aria-hidden="true">‹</span> Anterior</a>
    <h2>{{ $mes->locale('pt_BR')->translatedFormat('F \d\e Y') }}</h2>
    <a class="botao-contorno" href="{{ route('prazos', ['mes' => $proximo]) }}" >Próximo <span aria-hidden="true">›</span></a>
    @if (! $mes->isSameMonth(now()))
        <a href="{{ route('prazos') }}">Voltar a hoje</a>
    @endif
</div>

<table class="calendario">
    <caption class="so-leitor">Calendário de {{ $mes->locale('pt_BR')->translatedFormat('F \d\e Y') }}</caption>
    <thead>
        <tr>@foreach ($semana as $d) <th scope="col" abbr="{{ $d }}">{{ $d }}</th> @endforeach</tr>
    </thead>
    <tbody>
    @foreach ($semanas as $linha)
        <tr>
        @foreach ($linha as $c)
            <td @class(['fora' => ! $c['do_mes'], 'hoje' => $c['hoje']])>
                <span class="dia">{{ $c['data']->day }}@if ($c['hoje']) <span class="so-leitor"> (hoje)</span>@endif</span>
                @foreach ($c['prazos'] as $p)
                    <a href="{{ route('documentos.show', $p->documento_id) }}" class="{{ $dias($p) >= 0 && $dias($p) <= 7 ? 'urgente' : '' }}" title="{{ $p->descricao }} — {{ $p->documento->titulo }}">{{ $p->descricao }}</a>
                @endforeach
            </td>
        @endforeach
        </tr>
    @endforeach
    </tbody>
</table>

<section class="secao" aria-labelledby="prox">
    <h2 id="prox">A vencer</h2>
    @if ($proximos->isEmpty())
        <div class="lista-vazia"><p>Nenhum prazo futuro. Quando um documento arquivado trouxer datas, elas aparecem aqui.</p></div>
    @else
        <ul class="lista-prazos">
            @foreach ($proximos as $p)
                @php $d = $dias($p); @endphp
                <li>
                    <time class="quando" datetime="{{ $p->data->toDateString() }}">{{ $p->data->format('d/m/Y') }}</time>
                    <span>{{ $p->descricao }} · <a href="{{ route('documentos.show', $p->documento_id) }}">{{ $p->documento->titulo }}</a></span>
                    <span class="faltam {{ $d <= 7 ? 'urgente' : '' }}">{{ $quando($d) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>

@if ($vencidos->isNotEmpty())
<section class="secao" aria-labelledby="venc">
    <h2 id="venc">Vencidos</h2>
    <ul class="lista-prazos">
        @foreach ($vencidos as $p)
            <li>
                <time class="quando" datetime="{{ $p->data->toDateString() }}">{{ $p->data->format('d/m/Y') }}</time>
                <span>{{ $p->descricao }} · <a href="{{ route('documentos.show', $p->documento_id) }}">{{ $p->documento->titulo }}</a></span>
                <span class="faltam mut">{{ $quando($dias($p)) }}</span>
            </li>
        @endforeach
    </ul>
</section>
@endif
@endsection
