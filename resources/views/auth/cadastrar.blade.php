@extends('layout')
@section('titulo', 'Criar conta')
@section('erros-no-campo', '1')
@section('conteudo')
<div class="capa">
    <div class="capa-lado">
        <h1>Docs<i>IA</i></h1>
        <p>Cada equipe tem o seu espaço de trabalho. Você cria o primeiro agora e convida quem já tem conta.</p>
        <span class="carimbo neutro">Novo registro</span>
    </div>
    <div class="capa-form">
        <h2>Criar conta</h2>
        <form method="POST" action="{{ route('cadastrar') }}" novalidate>
            @csrf
            <div class="campo">
                <label for="name">Nome</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required
                       @error('name') aria-invalid="true" aria-describedby="name-erro" @enderror>
                @error('name') <p class="erro-campo" id="name-erro">{{ $message }}</p> @enderror
            </div>
            <div class="campo">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" spellcheck="false" required
                       @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror>
                @error('email') <p class="erro-campo" id="email-erro">{{ $message }}</p> @enderror
            </div>
            <div class="campo">
                <label for="password">Senha</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="password-dica @error('password') password-erro @enderror"
                       @error('password') aria-invalid="true" @enderror>
                <p class="dica" id="password-dica">Pelo menos 8 caracteres.</p>
                @error('password') <p class="erro-campo" id="password-erro">{{ $message }}</p> @enderror
            </div>
            <div class="campo">
                <label for="password_confirmation">Repita a senha</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
            <button type="submit" class="botao">Criar conta</button>
        </form>
        <p class="pequeno" style="margin-top:1.25rem">Já tem conta? <a href="{{ route('entrar') }}">Entrar</a></p>
    </div>
</div>
@endsection
