@extends('layout')
@section('titulo', 'Espaços de trabalho')
@section('erros-no-campo', '1')
@section('conteudo')
<div class="topo-pagina">
    <div>
        <h1>Espaços de trabalho</h1>
        <p>Cada espaço tem seu próprio arquivo, prazos e conversas. Só os membros enxergam o que está nele.</p>
    </div>
</div>

@foreach ($espacos as $e)
    @php $sou_dono = $e->dono_id === auth()->id(); @endphp
    <section class="espaco-cartao" aria-labelledby="esp-{{ $e->id }}">
        <div>
            <h2 id="esp-{{ $e->id }}" class="titulo-cartao">{{ $e->nome }}</h2>
            <p class="legenda">{{ trans_choice(':count documento|:count documentos', $e->documentos_count) }} · criado em {{ $e->created_at->format('d/m/Y') }}</p>
            @if ($e->id === $espacoAtual->id)
                <p><span class="estado pronto">Aberto agora</span></p>
            @else
                <form method="POST" action="{{ route('espacos.trocar', $e) }}">
                    @csrf
                    <button type="submit" class="botao-contorno">Abrir este espaço</button>
                </form>
            @endif
        </div>
        <div>
            <h3>Membros</h3>
            <ul class="membros">
                @foreach ($e->membros as $m)
                    <li>
                        <span>{{ $m->name }} <span class="mut mono">{{ $m->email }}</span>@if ($m->id === $e->dono_id) · dono @endif</span>
                        @if ($sou_dono && $m->id !== $e->dono_id)
                            <form method="POST" action="{{ route('espacos.membros.destroy', [$e, $m]) }}" data-confirmar="Remover {{ $m->name }} do espaço “{{ $e->nome }}”? A pessoa perde o acesso aos documentos.">
                                @csrf @method('DELETE')
                                <button type="submit" class="botao-link">Remover…</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($sou_dono)
                <form method="POST" action="{{ route('espacos.membros.store', $e) }}" class="form-linha" style="margin-top:.9rem" novalidate>
                    @csrf
                    <div class="campo">
                        <label for="email-{{ $e->id }}">Adicionar membro (e-mail da conta)</label>
                        <input id="email-{{ $e->id }}" name="email" type="email" autocomplete="off" spellcheck="false"
                               value="{{ old('email') }}" @if ($errors->has('email') && old('_espaco') == $e->id) aria-invalid="true" aria-describedby="email-erro-{{ $e->id }}" @endif>
                        @if ($errors->has('email') && old('_espaco') == $e->id) <p class="erro-campo" id="email-erro-{{ $e->id }}">{{ $errors->first('email') }}</p> @endif
                    </div>
                    <input type="hidden" name="_espaco" value="{{ $e->id }}">
                    <button type="submit" class="botao-contorno" style="margin-top:1.45rem">Adicionar</button>
                </form>
            @endif
        </div>
    </section>
@endforeach

<section class="secao" aria-labelledby="novo-espaco">
    <h2 id="novo-espaco">Criar outro espaço</h2>
    <form method="POST" action="{{ route('espacos.store') }}" class="form-linha" novalidate>
        @csrf
        <div class="campo">
            <label for="nome">Nome do espaço</label>
            <input id="nome" name="nome" type="text" maxlength="80" autocomplete="off" required
                   @error('nome') aria-invalid="true" aria-describedby="nome-erro" @enderror>
            @error('nome') <p class="erro-campo" id="nome-erro">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="botao" style="margin-top:1.45rem">Criar e abrir</button>
    </form>
</section>
@endsection
