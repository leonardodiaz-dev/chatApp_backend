<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('conversations')->group(function () {
    Route::get('/', [ConversationController::class, 'getAllConversations']);
    Route::get('/contact', [ConversationController::class, 'getContacts']);
    Route::put('/',[ConversationController::class,'update']);
    Route::post('/', [ConversationController::class, 'store']);
    Route::get('/unread', [ConversationController::class, 'getUnreadConversations']);
    Route::get('/{idConversation}', [ConversationController::class, 'getConversationById']);
    Route::get('/users/{idConversation}', [ConversationController::class, 'getOthersUsersByConversation']);
});

Route::put('/users', [UserController::class, 'update'])->middleware('auth:sanctum');
Route::get('/users/find-user/{busqueda}', [UserController::class, 'findUsers'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('messages')->group(function () {

    Route::get('{idConversation}', [MessageController::class, 'getMessagesByIdConversation']);
    Route::post('/', [MessageController::class, 'store']);
    Route::patch('{id}/delivered', [MessageController::class, 'delivered']);
    Route::patch('{conversationId}/read', [MessageController::class, 'read']);
});
