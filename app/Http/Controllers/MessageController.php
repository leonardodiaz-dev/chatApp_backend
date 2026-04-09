<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function getMessagesByIdConversation($idConversation)
    {
        try {
            $userId = Auth::id();

            $messages = Message::where('conversation_id', $idConversation)
                ->orderBy('created_at')
                ->get()
                ->map(function ($message) use ($userId) {
                    return [
                        'id'      => $message->id,
                        'content' => $message->content,
                        'mine'    => $message->user_id === $userId,
                        'date' => $message->created_at->toISOString(),
                    ];
                });

            return response()->json([
                'message' => 'Mensajes obtenidos correctamente',
                'data'   => $messages
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'Error' => $th->getMessage()
            ], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            $userId = Auth::id();
            $validator = Validator::make($request->all(), [
                'content' => 'required|string',
                'conversation_id' => 'required|exists:conversations,id',
            ]);
            if ($validator->fails()) {
                return response()->json(
                    [
                        'message' => 'Datos de validacion invalidos',
                        'error' => $validator->errors()
                    ],
                    422
                );
            }
            $data = $validator->validated();
            $message = Message::create([
                'content' => $data['content'],
                'conversation_id' => $data['conversation_id'],
                'user_id' => $userId,
            ]);
            Conversation::where('id', $data['conversation_id'])
                ->update([
                    'last_message' =>$data['content'],
                    'last_message_at' => now()
                ]);
            $formated_message = [
                'id' => $message->id,
                'content' => $message->content,
                'mine' => true,
                'time' => $message->created_at->format('g:i A'),
                'date' => $message->created_at
            ];
            return response()->json([
                'message' => 'Message enviado con exito',
                'data'   => $formated_message
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'Error' => $th->getMessage()
            ], 500);
        }
    }
}
