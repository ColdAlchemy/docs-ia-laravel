@extends('layout')
@section('titulo', 'Arquivo')
@section('conteudo')
<div class="topo-pagina">
    <div>
        <h1>Arquivo</h1>
        <p>
            @if ($totalNoEspaco === 0)
                Nenhum documento em <strong>{{ $espacoAtual->nome }}</strong> ainda.
            @else
                {{ trans_choice(':count documento|:count documentos', $totalNoEspaco) }} em <strong>{{ $espacoAtual->nome }}</strong>.
            @endif
        </p>
    </div>
    <div class="topo-acoes">
        <a class="botao" href="{{ route('documentos.create') }}">Arquivar documento</a>
    </div>
</div>

@if ($totalNoEspaco > 0)
<form method="GET" action="{{ route('documentos.index') }}" class="filtros" role="search" aria-label="Filtrar documentos">
    <div class="campo">
        <label for="q">Título contém</label>
        <input id="q" name="q" type="search" value="{{ $filtros['q'] ?? '' }}" autocomplete="off" spellcheck="false">
    </div>
    <div class="campo">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            @foreach (['contrato' => 'Contrato', 'notificacao' => 'Notificação', 'peticao' => 'Petição', 'outro' => 'Outro'] as $v => $r)
                <option value="{{ $v }}" @selected(($filtros['tipo'] ?? '') === $v)>{{ $r }}</option>
            @endforeach
        </select>
    </div>
    <div class="campo">
        <label for="status">Estado</label>
        <select id="status" name="status">
            <option value="">Todos</option>
            @foreach (['pronto' => 'Arquivado', 'processando' => 'Processando', 'pendente' => 'Na fila', 'erro' => 'Com erro'] as $v => $r)
                <option value="{{ $v }}" @selected(($filtros['status'] ?? '') === $v)>{{ $r }}</option>
            @endforeach
        </select>
    </div>
    <div class="filtros-acoes">
        <button type="submit" class="botao-contorno">Filtrar</button>
        @if (array_filter($filtros))
            <a href="{{ route('documentos.index') }}">Limpar</a>
        @endif
    </div>
</form>
@endif

@if ($documentos->isEmpty())
    <div class="lista-vazia">
        @if ($totalNoEspaco === 0)
            <h3>O arquivo está vazio</h3>
            <p>Arquive um contrato, uma notificação ou uma política (texto colado, .txt, .md ou PDF com texto). A IA extrai partes, prazos e valores, e você passa a poder perguntar ao documento.</p>
            <a class="botao" href="{{ route('documentos.create') }}">Arquivar o primeiro documento</a>
        @else
            <h3>Nada com esses filtros</h3>
            <p>Nenhum documento corresponde à busca. <a href="{{ route('documentos.index') }}">Limpar os filtros</a>.</p>
        @endif
    </div>
@else
    <table class="tabela">
        <caption class="so-leitor">Documentos arquivados em {{ $espacoAtual->nome }}</caption>
        <thead>
            <tr>
                <th scope="col">Registro</th>
                <th scope="col">Documento</th>
                <th scope="col">Tipo</th>
                <th scope="col">Próximo prazo</th>
                <th scope="col">Estado</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($documentos as $d)
            @php
                $prazo = $d->proximo_prazo ? \Carbon\Carbon::parse($d->proximo_prazo) : null;
                $dias = $prazo ? (int) now()->startOfDay()->diffInDays($prazo->startOfDay(), false) : null;
            @endphp
            <tr>
                <td class="reg sem-rotulo">{{ $d->registro() }}</td>
                <td class="sem-rotulo">
                    <div class="titulo-doc"><a href="{{ route('documentos.show', $d) }}">{{ $d->titulo }}</a></div>
                    @if (! empty($d->dados['partes']))
                        <div class="sub">{{ implode(' · ', array_slice($d->dados['partes'], 0, 3)) }}</div>
                    @endif
                </td>
                <td data-rotulo="Tipo">{{ $d->rotuloTipo() }}</td>
                <td class="num" data-rotulo="Próximo prazo">
                    @if ($prazo)
                        <time datetime="{{ $prazo->toDateString() }}" @class(['urgente' => $dias <= 7])>{{ $prazo->format('d/m/Y') }}</time>
                        <div class="sub {{ $dias <= 7 ? 'urgente' : '' }}">{{ $dias === 0 ? 'hoje' : ($dias === 1 ? 'amanhã' : "em {$dias} dias") }}</div>
                    @else
                        —
                    @endif
                </td>
                <td data-rotulo="Estado"><span class="estado {{ $d->status }}">{{ $d->rotuloStatus() }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="paginacao">{{ $documentos->links() }}</div>
@endif
@endsection
