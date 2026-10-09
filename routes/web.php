<?php

use App\Http\Controllers\DocumentoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/documentos');

Route::resource('documentos', DocumentoController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
Route::post('documentos/{documento}/reprocessar', [DocumentoController::class, 'reprocessar'])->name('documentos.reprocessar');

Route::view('/chat', 'chat')->name('chat');
