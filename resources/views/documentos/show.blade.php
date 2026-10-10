@extends('layout')
@section('titulo', $documento->titulo)
@section('conteudo')
@php
    $proximoPrazo = $documento->prazos->first(fn ($p) => $p->data->gte(now()->startOfDay()));
    $emProcesso = in_array($documento->status, ['pendente', 'processando'], true);
    $origem = match ($documento->origem) { 'pdf' => 'PDF', 'arquivo' => 'arquivo de texto', default => 'texto colado' };
    $config = [
        'modo' => 'leitor',
        'endpoint' => route('api.perguntar'),
        'documentoId' => $documento->id,
        'conversaId' => $conversa?->id,
        'mensagens' => $mensagens,
        'pronto' => $documento->estaPronto(),
        'sugestoes' => ['Qual é o prazo e o valor a pagar?', 'Quem são as partes?', 'O que acontece se houver atraso?'],
    ];
@endphp

<header class="leitor-cabecalho">
    <p class="reg mono" translate="no">Registro {{ $documento->registro() }}</p>
    <h1>{{ $documento->titulo }}</h1>
    <div class="leitor-meta">
        <span class="estado {{ $documento->status }}">{{ $documento->rotuloStatus() }}</span>
        <span>{{ $documento->rotuloTipo() }}</span>
        <span>{{ $origem }}@if ($documento->arquivo_nome) · {{ $documento->arquivo_nome }}@endif</span>
        <span>enviado em {{ $documento->created_at->format('d/m/Y') }}@if ($documento->autor) por {{ $documento->autor->name }}@endif</span>
        <span>{{ $documento->trechos_count }} {{ $documento->trechos_count === 1 ? 'trecho indexado' : 'trechos indexados' }}</span>
        @if ($proximoPrazo)
            @php $dias = (int) now()->startOfDay()->diffInDays($proximoPrazo->data->startOfDay(), false); @endphp
            <span class="{{ $dias <= 7 ? 'urgente' : '' }}">próximo prazo {{ $proximoPrazo->data->format('d/m/Y') }} ({{ $dias === 0 ? 'hoje' : ($dias === 1 ? 'amanhã' : "em {$dias} dias") }})</span>
        @endif
    </div>
    <div class="acoes-doc acoes-tela">
        <a class="botao-contorno" href="{{ route('documentos.relatorio', $documento) }}">Relatório para imprimir</a>
        <form method="POST" action="{{ route('documentos.reprocessar', $documento) }}">
            @csrf
            <button type="submit" class="botao-contorno">Reprocessar</button>
        </form>
        <form method="POST" action="{{ route('documentos.destroy', $documento) }}"
              data-confirmar="O documento “{{ $documento->titulo }}”, seus trechos, prazos e conversas serão removidos do arquivo. Isso não pode ser desfeito.">
            @csrf @method('DELETE')
            <button type="submit" class="botao-perigo">Excluir…</button>
        </form>
    </div>
</header>

@if ($emProcesso)
    <div class="aviso" role="status" data-atualizar="3000">
        O documento está na fila de processamento. Esta página se atualiza sozinha quando terminar.
        <span class="mut">(Em desenvolvimento, rode <span class="mono">php artisan queue:work</span> ou use <span class="mono">QUEUE_CONNECTION=sync</span>.)</span>
    </div>
@elseif ($documento->status === 'erro')
    <div class="aviso erro" role="alert">
        <p><strong>Não foi possível processar este documento.</strong> {{ $documento->erro }}</p>
        <p>Use “Reprocessar” para tentar de novo.</p>
    </div>
@endif

<div class="leitor">
    <article class="folha" aria-label="Texto do documento">
        @if (! empty($documento->dados['resumo']))
            <div class="resumo-ia">
                <p class="legenda">Resumo gerado pela IA</p>
                <p>{{ $documento->dados['resumo'] }}</p>
            </div>
        @endif

        @foreach ($blocos as $b)
            <div @class(['bloco', 'marcado' => $b['id'] && $b['id'] === $destaque]) @if ($b['id']) id="T{{ $b['id'] }}" data-trecho="{{ $b['id'] }}" tabindex="-1" @endif>
                <div class="bloco-texto">{{ $b['texto'] }}</div>
                @if ($b['notas'])
                    <aside class="margem" aria-label="Anotações deste trecho">
                        @foreach ($b['notas'] as $n)
                            <div class="nota {{ $n['tipo'] }}">
                                <b>{{ $n['rotulo'] }}</b>
                                <i>{{ $n['detalhe'] }}</i>
                            </div>
                        @endforeach
                    </aside>
                @endif
            </div>
        @endforeach
    </article>

    <section class="painel" aria-labelledby="titulo-perguntas">
        <h2 id="titulo-perguntas">Pergunte a este documento</h2>
        <p class="pequeno mut">As respostas vêm só dos trechos acima, com a fonte numerada em nota de rodapé.</p>
        @if (! $documento->estaPronto())
            <p class="pequeno mut">As perguntas ficam disponíveis quando o documento terminar de ser processado.</p>
        @endif
        <div id="perguntas-app" data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) }}"></div>
    </section>
</div>
@endsection
