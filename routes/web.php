<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AskStreamController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ask', [AskController::class, 'index'])->name('ask.index');
    Route::post('/ask', [AskController::class, 'ask'])->name('ask.post');

    Route::get('/ask-stream', [AskStreamController::class, 'index'])
        ->name('stream.index');
    Route::post('/ask-stream', [AskStreamController::class, 'stream'])
        ->name('stream.post');

    Route::redirect('/dashboard', '/chat')->name('dashboard');
});

Route::middleware('auth')->group(function () {

    Route::get('/chat',
        [ConversationController::class, 'index']);

    Route::post('/chat',
        [ConversationController::class, 'store']);

    Route::get('/chat/{conversation}',
        [ConversationController::class, 'show']);

    Route::post('/chat/{conversation}/message',
        [MessageController::class, 'store']);
        
    Route::delete('/chat/{conversation}',
        [ConversationController::class, 'destroy']);
    
});

require __DIR__.'/settings.php';
