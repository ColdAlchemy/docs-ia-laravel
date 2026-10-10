@extends('layout')
@section('titulo', 'Relatório — '.$documento->titulo)
@section('conteudo')
<div class="topo-pagina acoes-tela">
    <div>
        <p class="titulo-pagina">Relatório</p>
        <p>Use “Imprimir” e escolha “Salvar como PDF” para guardar uma cópia.</p>
    </div>
    <div class="topo-acoes">
        <button type="button" class="botao" data-imprimir>Imprimir</button>
        <a class="botao-contorno" href="{{ route('documentos.show', $documento) }}">Voltar ao documento</a>
    </div>
</div>

<article class="relatorio">
    <p class="mono pequeno mut">DocsIA · Registro {{ $documento->registro() }} · emitido em {{ now()->format('d/m/Y H:i') }}</p>
    <h1>{{ $documento->titulo }}</h1>

    <h2>Ficha</h2>
    <dl>
        <dt>Tipo</dt><dd>{{ $documento->rotuloTipo() }}</dd>
        <dt>Estado</dt><dd>{{ $documento->rotuloStatus() }}</dd>
        <dt>Enviado em</dt><dd>{{ $documento->created_at->format('d/m/Y') }}@if ($documento->autor) por {{ $documento->autor->name }}@endif</dd>
        <dt>Partes</dt><dd>{{ implode(', ', $documento->dados['partes'] ?? []) ?: 'Nenhuma identificada' }}</dd>
    </dl>

    @if (! empty($documento->dados['resumo']))
        <h2>Resumo (gerado pela IA)</h2>
        <p>{{ $documento->dados['resumo'] }}</p>
    @endif

    <h2>Prazos</h2>
    @forelse ($documento->prazos as $p)
        <p><span class="mono">{{ $p->data->format('d/m/Y') }}</span> — {{ $p->descricao }}</p>
    @empty
        <p class="mut">Nenhum prazo extraído.</p>
    @endforelse

    <h2>Valores</h2>
    @forelse ($documento->dados['valores'] ?? [] as $v)
        <p><span class="mono">R$ {{ $v['valor'] }}</span> — {{ $v['descricao'] }}</p>
    @empty
        <p class="mut">Nenhum valor extraído.</p>
    @endforelse

    @if ($conversas->isNotEmpty())
        <h2>Perguntas e respostas</h2>
        @foreach ($conversas as $c)
            @foreach ($c->mensagens as $m)
                <div class="qa">
                    @if ($m->papel === 'user')
                        <p><strong>Pergunta:</strong> {{ $m->conteudo }}</p>
                    @else
                        <p style="white-space:pre-line">{{ $m->conteudo }}</p>
                        <p class="pequeno mut">{{ $m->fundamentada ? 'Resposta fundamentada nos trechos citados.' : 'Atenção: resposta sem fundamento verificável.' }}</p>
                    @endif
                </div>
            @endforeach
        @endforeach
    @endif

    <h2>Texto integral</h2>
    <div class="texto-integral">{{ $documento->conteudo }}</div>

    <p class="pequeno mut" style="margin-top:2rem">Este relatório não constitui aconselhamento jurídico. Dados extraídos e respostas foram gerados por IA a partir do texto acima.</p>
</article>
@endsection
