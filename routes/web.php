<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EspacoController;
use App\Http\Controllers\PrazoController;
use App\Http\Controllers\QualidadeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'formEntrar'])->name('entrar');
    Route::post('/entrar', [AuthController::class, 'entrar'])->middleware('throttle:6,1');
    Route::get('/cadastrar', [AuthController::class, 'formCadastrar'])->name('cadastrar');
    Route::post('/cadastrar', [AuthController::class, 'cadastrar'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'espaco'])->group(function () {
    Route::redirect('/', '/documentos');
    Route::post('/sair', [AuthController::class, 'sair'])->name('sair');

    // Arquivo e leitor
    Route::get('documentos/novo', [DocumentoController::class, 'create'])->name('documentos.create');
    Route::resource('documentos', DocumentoController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('documentos/{documento}/relatorio', [DocumentoController::class, 'relatorio'])->name('documentos.relatorio');
    Route::post('documentos/{documento}/reprocessar', [DocumentoController::class, 'reprocessar'])->name('documentos.reprocessar');

    // Consultas (histórico de conversas)
    Route::get('consultas/{conversa?}', [ConsultaController::class, 'index'])->name('consultas');
    Route::delete('consultas/{conversa}', [ConsultaController::class, 'destroy'])->name('consultas.destroy');

    // Prazos
    Route::get('prazos', [PrazoController::class, 'index'])->name('prazos');
    Route::get('prazos.ics', [PrazoController::class, 'ics'])->name('prazos.ics');

    // Qualidade das respostas
    Route::get('qualidade', [QualidadeController::class, 'index'])->name('qualidade');
    Route::post('qualidade/perguntas', [QualidadeController::class, 'store'])->name('qualidade.perguntas.store');
    Route::delete('qualidade/perguntas/{pergunta}', [QualidadeController::class, 'destroy'])->name('qualidade.perguntas.destroy');
    Route::post('qualidade/executar', [QualidadeController::class, 'executar'])->middleware('throttle:6,1')->name('qualidade.executar');

    // Espaços de trabalho
    Route::get('espacos', [EspacoController::class, 'index'])->name('espacos');
    Route::post('espacos', [EspacoController::class, 'store'])->name('espacos.store');
    Route::post('espacos/{espaco}/trocar', [EspacoController::class, 'trocar'])->name('espacos.trocar');
    Route::post('espacos/{espaco}/membros', [EspacoController::class, 'adicionarMembro'])->name('espacos.membros.store');
    Route::delete('espacos/{espaco}/membros/{usuario}', [EspacoController::class, 'removerMembro'])->name('espacos.membros.destroy');

    // JSON (sessão + CSRF)
    Route::post('api/perguntar', [ChatController::class, 'perguntar'])->middleware('throttle:30,1')->name('api.perguntar');
    Route::get('api/busca', [ChatController::class, 'busca'])->middleware('throttle:60,1')->name('api.busca');
});
