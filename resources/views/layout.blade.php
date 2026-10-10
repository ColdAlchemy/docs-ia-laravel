<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4ede0">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Arquivo') — DocsIA</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='3' fill='%2314213d'/%3E%3Ctext x='16' y='23' font-family='Georgia,serif' font-size='20' font-weight='700' text-anchor='middle' fill='%23fbf7ee'%3ED%3C/text%3E%3Ccircle cx='25' cy='7' r='3' fill='%23b3261e'/%3E%3C/svg%3E">
    @foreach (['node_modules/@fontsource-variable/newsreader/files/newsreader-latin-opsz-normal.woff2', 'node_modules/@fontsource/ibm-plex-mono/files/ibm-plex-mono-latin-400-normal.woff2'] as $fonte)
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset($fonte) }}">
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>

<header class="cabecalho">
    <div class="cabecalho-miolo">
        <a class="marca" href="{{ route('documentos.index') }}" translate="no">
            <strong>Docs<i>IA</i></strong>
            <small>arquivo de cartório</small>
        </a>

        @auth
        <nav class="nav" aria-label="Principal">
            <a href="{{ route('documentos.index') }}" @if (request()->routeIs('documentos.*')) aria-current="page" @endif>Arquivo</a>
            <a href="{{ route('consultas') }}" @if (request()->routeIs('consultas*')) aria-current="page" @endif>Consultas</a>
            <a href="{{ route('prazos') }}" @if (request()->routeIs('prazos')) aria-current="page" @endif>Prazos</a>
            <a href="{{ route('qualidade') }}" @if (request()->routeIs('qualidade*')) aria-current="page" @endif>Qualidade</a>
        </nav>

        <div class="conta">
            <details class="espaco-menu">
                <summary aria-label="Espaço de trabalho atual: {{ $espacoAtual->nome }}. Abrir lista de espaços">{{ $espacoAtual->nome }}</summary>
                <div class="espaco-lista">
                    @foreach ($meusEspacos as $e)
                        <form method="POST" action="{{ route('espacos.trocar', $e) }}">
                            @csrf
                            <button type="submit" @if ($e->id === $espacoAtual->id) aria-current="true" @endif>{{ $e->nome }}@if ($e->id === $espacoAtual->id) (aberto)@endif</button>
                        </form>
                    @endforeach
                    <hr>
                    <a href="{{ route('espacos') }}">Gerenciar espaços…</a>
                </div>
            </details>
            <span class="mut">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('sair') }}">
                @csrf
                <button type="submit" class="botao-link">Sair</button>
            </form>
        </div>
        @endauth
    </div>
</header>

<main id="conteudo" tabindex="-1">
    @if (session('ok'))
        <div class="aviso" role="status">{{ session('ok') }}</div>
    @endif
    @if ($errors->any() && ! View::hasSection('erros-no-campo'))
        <div class="aviso erro" role="alert">
            @foreach ($errors->all() as $e) <p>{{ $e }}</p> @endforeach
        </div>
    @endif
    @yield('conteudo')
</main>

<footer class="rodape">
    <p>Projeto de portfólio. Desenvolvido por mim com o auxílio do Claude Code. Os documentos de exemplo são fictícios e a aplicação não dá aconselhamento jurídico.</p>
</footer>

<dialog id="confirmar" aria-labelledby="confirmar-titulo">
    <form method="dialog">
        <h2 id="confirmar-titulo">Confirmar</h2>
        <p id="confirmar-texto"></p>
        <div class="acoes">
            <button class="botao-contorno" value="cancelar">Cancelar</button>
            <button class="botao-perigo" value="ok" id="confirmar-ok">Confirmar</button>
        </div>
    </form>
</dialog>
</body>
</html>
