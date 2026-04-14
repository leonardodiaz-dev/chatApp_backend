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
Route::post('/register',[AuthController::class,'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/conversations/contact',[ConversationController::class,'getContacts'])->middleware('auth:sanctum');
Route::get('/conversations',[ConversationController::class,'getAllConversations'])->middleware('auth:sanctum');
Route::post('/conversations',[ConversationController::class,'store'])->middleware('auth:sanctum');
Route::get('/conversations/unread',[ConversationController::class,'getUnreadConversations'])->middleware('auth:sanctum');
Route::get('/conversations/{idConversation}',[ConversationController::class,'getConversationById'])->middleware('auth:sanctum');

Route::get('/users/find-user/{busqueda}',[UserController::class,'findUsers'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('messages')->group(function () {

    Route::get('{idConversation}', [MessageController::class, 'getMessagesByIdConversation']);
    Route::post('/', [MessageController::class, 'store']);
    Route::patch('{id}/delivered', [MessageController::class, 'delivered']);
    Route::patch('{conversationId}/read',[MessageController::class,'read']);
});