<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', [ChatController::class, 'chat'])->middleware('throttle:30,1')->name('api.chat');
Route::get('/busca', [ChatController::class, 'busca'])->middleware('throttle:60,1')->name('api.busca');
