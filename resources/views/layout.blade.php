<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'DocsIA')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<header>
    <strong>DocsIA</strong>
    <a href="{{ route('documentos.index') }}">Documentos</a>
    <a href="{{ route('chat') }}">Perguntar à IA</a>
</header>
<main>
    @if (session('ok')) <p class="ok">{{ session('ok') }}</p> @endif
    @if ($errors->any())
        <div class="card erro">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
    @endif
    @yield('conteudo')
</main>
</body>
</html>
