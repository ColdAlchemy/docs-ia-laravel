@extends('layout')
@section('titulo', 'Entrar')
@section('erros-no-campo', '1')
@section('conteudo')
<div class="capa">
    <div class="capa-lado">
        <h1>Docs<i>IA</i></h1>
        <p>Contratos e notificações lidos, anotados e respondidos com a fonte na margem.</p>
        <span class="carimbo">Arquivo aberto</span>
    </div>
    <div class="capa-form">
        <h2>Entrar</h2>
        <form method="POST" action="{{ route('entrar') }}" novalidate>
            @csrf
            <div class="campo">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" spellcheck="false" required
                       @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror>
                @error('email') <p class="erro-campo" id="email-erro">{{ $message }}</p> @enderror
            </div>
            <div class="campo">
                <label for="password">Senha</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       @error('password') aria-invalid="true" aria-describedby="password-erro" @enderror>
                @error('password') <p class="erro-campo" id="password-erro">{{ $message }}</p> @enderror
            </div>
            <label class="linha-checkbox" for="lembrar">
                <input id="lembrar" name="lembrar" type="checkbox" value="1">
                Manter conectado neste aparelho
            </label>
            <button type="submit" class="botao">Entrar</button>
        </form>
        <p class="pequeno" style="margin-top:1.25rem">Ainda não tem conta? <a href="{{ route('cadastrar') }}">Criar uma conta</a></p>
        @if (app()->environment('local'))
            <p class="demo">Conta de demonstração (dados fictícios): <span class="mono">demo@docsia.test</span> · senha <span class="mono">demonstracao</span></p>
        @endif
    </div>
</div>
@endsection
