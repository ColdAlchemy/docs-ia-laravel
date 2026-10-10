@extends('layout')
@section('titulo', 'Qualidade')
@section('erros-no-campo', '1')
@section('conteudo')
<div class="topo-pagina">
    <div>
        <h1>Qualidade das respostas</h1>
        <p>Uma resposta é <em>fundamentada</em> quando cita trechos e todas as citações vêm de trechos realmente recuperados.</p>
    </div>
</div>

@if ($ind['total'] === 0)
    <div class="lista-vazia">
        <h3>Ainda não há respostas para avaliar</h3>
        <p>Faça perguntas em <a href="{{ route('consultas') }}">Consultas</a> ou no leitor de um documento, ou monte um conjunto de verificação abaixo e execute-o.</p>
    </div>
@else
    <p class="veredito">
        De <b class="mono">{{ $ind['total'] }}</b> {{ $ind['total'] === 1 ? 'resposta' : 'respostas' }},
        <b class="mono">{{ $ind['fundamentadas'] }}</b> {{ $ind['fundamentadas'] === 1 ? 'estava fundamentada' : 'estavam fundamentadas' }}
        nos documentos (<b class="mono">{{ $ind['taxa'] }}%</b>).
    </p>
    <div class="regua-taxa" role="img" aria-label="{{ $ind['taxa'] }}% das respostas fundamentadas">
        <span style="width: {{ $ind['taxa'] }}%"></span>
    </div>
    <p class="legenda">azul: fundamentadas · fundo vermelho: reprovadas</p>

    <dl class="fichas">
        <div><dt>Reprovadas</dt><dd>{{ $ind['reprovadas'] }}</dd></div>
        <div><dt>Sem trecho encontrado</dt><dd>{{ $ind['sem_fonte'] }}</dd></div>
        <div><dt>Citações inválidas</dt><dd>{{ $ind['invalidas'] }}</dd></div>
    </dl>

    <section class="secao" aria-labelledby="semanal">
        <h2 id="semanal">Por semana</h2>
        <table class="tabela" style="max-width:40rem">
            <thead><tr><th scope="col">Semana de</th><th scope="col">Respostas</th><th scope="col">Fundamentadas</th><th scope="col">Taxa</th></tr></thead>
            <tbody>
            @foreach ($semanas as $s)
                <tr @if ($s['total'] === 0) class="sem-dados-num" @endif>
                    <td class="num" data-rotulo="Semana de">{{ $s['semana']->format('d/m/Y') }}</td>
                    <td class="num" data-rotulo="Respostas">{{ $s['total'] }}</td>
                    <td class="num" data-rotulo="Fundamentadas">{{ $s['fundamentadas'] }}</td>
                    <td class="num" data-rotulo="Taxa">{{ $s['taxa'] === null ? '—' : $s['taxa'].'%' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    @if ($reprovadas->isNotEmpty())
    <section class="secao" aria-labelledby="rep">
        <h2 id="rep">Respostas reprovadas para revisar</h2>
        @foreach ($reprovadas as $m)
            <div class="reprovada">
                <p class="r">{{ \Illuminate\Support\Str::limit($m->conteudo, 220) }}</p>
                <p class="legenda">
                    {{ $m->avaliacao['motivo'] ?? 'Sem motivo registrado.' }} ·
                    <a href="{{ route('consultas', $m->conversa_id) }}">{{ $m->conversa->titulo }}</a>
                </p>
            </div>
        @endforeach
    </section>
    @endif
@endif

<section class="secao" aria-labelledby="verif">
    <h2 id="verif">Conjunto de verificação</h2>
    <p>Perguntas que você quer sempre ver bem respondidas. Execute o conjunto depois de arquivar ou alterar documentos.</p>

    <form method="POST" action="{{ route('qualidade.perguntas.store') }}" class="form-linha" novalidate style="margin-bottom:1.25rem">
        @csrf
        <div class="campo">
            <label for="pergunta">Nova pergunta de verificação</label>
            <input id="pergunta" name="pergunta" type="text" maxlength="300" autocomplete="off" value="{{ old('pergunta') }}"
                   @error('pergunta') aria-invalid="true" aria-describedby="pergunta-erro" @enderror>
            @error('pergunta') <p class="erro-campo" id="pergunta-erro">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="botao-contorno" style="margin-top:1.45rem">Adicionar</button>
    </form>

    @if ($verificacao->isEmpty())
        <div class="lista-vazia"><p>O conjunto está vazio. Adicione perguntas como “Qual é o prazo de pagamento?”.</p></div>
    @else
        <table class="tabela">
            <thead><tr><th scope="col">Pergunta</th><th scope="col">Último resultado</th><th scope="col">Executada em</th><th scope="col"><span class="so-leitor">Ações</span></th></tr></thead>
            <tbody>
            @foreach ($verificacao as $v)
                <tr>
                    <td class="sem-rotulo">{{ $v->pergunta }}
                        @if ($v->ultimo_motivo) <div class="sub">{{ $v->ultimo_motivo }}</div> @endif
                    </td>
                    <td data-rotulo="Resultado">
                        @if ($v->ultima_fundamentada === null)
                            <span class="mut">Ainda não executada</span>
                        @elseif ($v->ultima_fundamentada)
                            <span class="estado pronto">Fundamentada</span>
                        @else
                            <span class="estado erro">Sem fundamento</span>
                        @endif
                    </td>
                    <td class="num" data-rotulo="Executada em">{{ $v->executada_em?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="sem-rotulo">
                        <form method="POST" action="{{ route('qualidade.perguntas.destroy', $v) }}" data-confirmar="Remover esta pergunta do conjunto de verificação?">
                            @csrf @method('DELETE')
                            <button type="submit" class="botao-link">Remover…</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <form method="POST" action="{{ route('qualidade.executar') }}" style="margin-top:1rem">
            @csrf
            <button type="submit" class="botao">Executar verificação</button>
        </form>
    @endif
</section>
@endsection
