<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AskStreamController;
use App\Http\Controllers\UserController;

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

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/chat',
        [ConversationController::class, 'index'])
        ->name('chat.index');

    Route::post('/chat',
        [ConversationController::class, 'store'])
        ->name('chat.store');

    Route::get('/chat/{conversation}',
        [ConversationController::class, 'show'])
        ->name('chat.show');

    Route::post('/chat/{conversation}/message',
        [MessageController::class, 'store'])
        ->name('chat.message.store');
        
    Route::delete('/chat/{conversation}',
        [ConversationController::class, 'destroy'])
        ->name('chat.destroy');
    
    Route::post('/user/settings', [UserController::class, 'updateSettings'])
        ->name('user.settings.update');
    
});

require __DIR__.'/settings.php';
