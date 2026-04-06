<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConversationController extends Controller
{
    public function store(Request $request)
    {
        try {

            $userId = Auth::id();

            $validator = Validator::make($request->all(), [
                'type' => 'required|string',
                'user_id' => 'required|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            if ($userId == $data['user_id']) {
                return response()->json([
                    'message' => 'No puedes iniciar una conversación contigo mismo'
                ], 400);
            }

            DB::transaction(function () use ($data, $userId) {

                $conversation = Conversation::create([
                    'type' => $data['type']
                ]);

                $conversation->users()->attach([$userId, $data['user_id']]);
            });

            return response()->json([
                'message' => 'Conversación creada correctamente',
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getAllConversations()
    {
        try {
            $userId = Auth::id();
            $conversations = Conversation::with(['users' => function ($query) use ($userId) {
                $query->where('users.id', '!=', $userId);
            }, 'lastMessage'])->whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->get()
                ->map(function ($conversacion) {
                    return [
                        'id' => $conversacion->id,
                        'type' => $conversacion->type,
                        'users' =>  $conversacion->users->transform(function ($user) {
                            return [
                                'id'       => $user->id,
                                'name'     => $user->name,
                                'lastname' => $user->lastname
                            ];
                        }),
                        'last_message' => $conversacion->lastMessage->content,
                        'last_date' => $conversacion->lastMessage->created_at 
                    ];
                });
            return response()->json([
                'message' => 'Se obtuvieron correctamente las conversaciones',
                'data' => $conversations
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
