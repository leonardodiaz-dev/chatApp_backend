<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register',[AuthController::class,'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/conversations',[ConversationController::class,'getAllConversations'])->middleware('auth:sanctum');
Route::post('/conversations',[ConversationController::class,'store'])->middleware('auth:sanctum');

Route::get('/users/find-user/{busqueda}',[UserController::class,'findUsers'])->middleware('auth:sanctum');

Route::get('/messages/{idConversation}',[MessageController::class,'getMessagesByIdConversation'])->middleware('auth:sanctum');
Route::post('/messages',[MessageController::class,'store'])->middleware('auth:sanctum');