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
                'name' => 'required|string',
                'user_ids' => 'required|array',
                'user_ids.*' => 'integer|exits:users,id'
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

            DB::transaction(function () use ($data) {

                $conversation = Conversation::create([
                    'type' => $data['type'],
                    'name' => $data['name']
                ]);

                $conversation->users()->attach($data['user_ids']);
            });

            return response()->json([
                'message' => 'Conctacto agregado correctamente',
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getContacts()
    {
        try {
            $userId = Auth::id();
            $contacts = Conversation::whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->with(['users' => function ($q) use ($userId) {
                    $q->where('users.id', '!=', $userId)
                        ->select('users.id', 'users.name','users.lastname');
                }])
                ->get();

            $contacts_filter = $contacts->flatMap->users;
            return response()->json([
                'message' => 'Contactos obtenidos correctamente',
                'data' => $contacts_filter
            ]);
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
            $conversations = Conversation::with(['lastMessage'])
                ->whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->get()
                ->map(function ($conversacion) {
                    return [
                        'id' => $conversacion->id,
                        'type' => $conversacion->type,
                        'name' => $conversacion->name,
                        'last_message' => $conversacion->lastMessage->content ?? '',
                        'last_date' => $conversacion->lastMessage->created_at ?? ''
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
