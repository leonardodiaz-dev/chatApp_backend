<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ConversationController extends Controller
{
    public function store(Request $request)
    {
        try {
            $userId = Auth::id();

            $validator = Validator::make($request->all(), [
                'type'      => 'required|string|in:group,private',
                'name'      => 'required|string|max:255',
                'image'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'user_ids'  => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $participants = collect(explode(',', $data['user_ids']))
                ->map(fn($id) => (int) trim($id))
                ->filter();

            $usersExists = User::whereIn('id', $participants)->pluck('id');

            if ($usersExists->count() !== $participants->count()) {
                return response()->json([
                    'message' => 'Uno o más usuarios no existen'
                ], 422);
            }

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('conversations', 'public');
            }

            $conversation = DB::transaction(function () use ($data, $userId, $participants, $imagePath) {

                $conversation = Conversation::create([
                    'type'   => $data['type'],
                    'name'   => $data['name'],
                    'avatar' => $imagePath,
                ]);

                if (!$participants->contains($userId)) {
                    $participants->push($userId);
                }

                $conversation->users()->attach($participants->unique()->toArray());

                return $conversation;
            });

            return response()->json([
                'message' => 'Conversacion creada correctamente',
                'data' => $conversation
            ], 201);
        } catch (\Throwable $th) {

            if (isset($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

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
            $contacts = User::whereHas('conversations', function ($q) use ($userId) {
                $q->whereHas('users', fn($q2) => $q2->where('users.id', $userId));
            })
                ->where('id', '!=', $userId)
                ->select('id', 'name', 'lastname')
                ->distinct()
                ->get();

            return response()->json([
                'message' => 'Contactos obtenidos correctamente',
                'data' => $contacts
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getUnreadConversations()
    {
        try {
            $userId = Auth::id();
            $conversations = Conversation::whereHas('messages', function ($q) {
                $q->whereIn('status', ['enviado', 'entregado']);
            })->whereHas('users', fn($q) => $q->where('users.id', $userId))
               ->with(['users' => function ($q) use ($userId) {
                    $q->where('users.id', '!=', $userId);
                }])
                ->withCount(['messages' => function ($q) use($userId) {
                    $q->whereIn('status', ['enviado', 'entregado'])
                     ->where('user_id','!=',$userId);;
                }])
                ->get()
                 ->map(function ($conversacion) {
                    return [
                        'id' => $conversacion->id,
                        'type' => $conversacion->type,
                        'name' => $conversacion->type === 'group'
                            ? $conversacion->name
                            : $conversacion->users->first()->name,
                        'messages_count' => $conversacion->messages_count,
                        'last_message' => $conversacion->last_message ?? '',
                        'last_date' => $conversacion->last_message_at?->toISOString(),
                    ];
                });
                return response()->json($conversations);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getAllConversations()
    {
        try {
            $userId = Auth::id();
            $conversations = Conversation::with(['users' => function ($q) use ($userId) {
                $q->where('users.id', '!=', $userId);
            }])->whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->orderByDesc('last_message_at')
                ->get()
                ->map(function ($conversacion) {
                    return [
                        'id' => $conversacion->id,
                        'type' => $conversacion->type,
                        'name' => $conversacion->type === 'group'
                            ? $conversacion->name
                            : $conversacion->users->first()->name,
                        'last_message' => $conversacion->last_message ?? '',
                        'last_date' => $conversacion->last_message_at?->toISOString(),
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
