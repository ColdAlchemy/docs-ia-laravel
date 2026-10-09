@extends('layout')
@section('titulo', 'Documentos')
@section('conteudo')
<div class="card">
    <a class="btn" href="{{ route('documentos.create') }}">Novo documento</a>
</div>
<div class="card">
    <table>
        <thead><tr><th>Título</th><th>Status</th><th>Trechos</th><th>Criado em</th></tr></thead>
        <tbody>
        @forelse ($documentos as $d)
            <tr>
                <td><a href="{{ route('documentos.show', $d) }}">{{ $d->titulo }}</a></td>
                <td><span class="etiqueta">{{ $d->status }}</span></td>
                <td>{{ $d->trechos_count }}</td>
                <td>{{ $d->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="mut">Nenhum documento ainda.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $documentos->links() }}
</div>
@endsection
