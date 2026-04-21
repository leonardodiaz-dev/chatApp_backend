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

                $usersWithRoles = $participants->unique()->mapWithKeys(function ($id) use ($userId) {
                    return [
                        $id => [
                            'role' => $id == $userId ? 'admin' : 'member'
                        ]
                    ];
                })->toArray();


                $conversation->users()->attach($usersWithRoles);

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
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'conversation_id' => 'required|exists:conversations,id',
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

            $usersWithRoles = collect($participants)->mapWithKeys(function ($id) {
                return [
                    $id => ['role' => 'member']
                ];
            })->toArray();

            DB::transaction(function () use ($usersWithRoles, $data) {
                $conversation = Conversation::findOrFail($data['conversation_id']);
                $conversation->users()->syncWithoutDetaching($usersWithRoles);
            });

            return response()->json(['message' => 'Usuarios agregados al grupo']);
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
            $conversations = Conversation::whereHas('messages', function ($q) use ($userId) {
                $q->whereIn('status', ['enviado', 'entregado'])
                    ->where('user_id', '!=', $userId);
            })->whereHas('users', fn($q) => $q->where('users.id', $userId))
                ->with(['users' => function ($q) use ($userId) {
                    $q->where('users.id', '!=', $userId);
                }])
                ->withCount(['messages' => function ($q) {
                    $q->whereIn('status', ['enviado', 'entregado']);
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

    public function getConversationById($idConversation)
    {
        try {
            $userId = Auth::id();

            $conversacion = Conversation::with(['users'])
                ->where('id', $idConversation)
                ->whereHas('users', function ($query) use ($userId) {
                    $query->where('users.id', $userId);
                })
                ->first();
            if (!$conversacion) {
                return response()->json(['message' => 'No tienes permiso o no existe'], 403);
            }
            $otherUser = $conversacion->users->firstWhere('id', '!=', $userId);
            $formated_conversation = [
                'id' => $conversacion->id,
                'type' => $conversacion->type,
                'name' => $conversacion->type === 'group'
                    ? $conversacion->name
                    :  $otherUser->name,
                'last_message' => $conversacion->last_message ?? '',
                'last_date' => $conversacion->last_message_at?->toISOString(),
                'avatar' => $conversacion->type === 'group'
                    ? $conversacion->avatar
                    : $otherUser->avatar,
                'users' => $conversacion->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar' => $user->avatar,
                        'role' => $user->pivot->role
                    ];
                })
            ];
            return response()->json($formated_conversation);
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
                            : $conversacion->users->first()->name . " " . $conversacion->users->first()->lastname,
                        'last_message' => $conversacion->last_message ?? '',
                        'last_date' => $conversacion->last_message_at?->toISOString(),
                        'avatar' => $conversacion->type === 'group'
                            ? $conversacion->avatar
                            : $conversacion->users->first()->avatar
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

    public function getOthersUsersByConversation($idConversation)
    {
        try {
            $userId = Auth::id();

            $usersInConversation = Conversation::find($idConversation)
                ->users()
                ->pluck('users.id');

            $users = User::whereHas('conversations', function ($q) use ($userId) {
                $q->whereHas('users', fn($query) => $query->where('users.id', $userId));
            })->whereNotIn('id', $usersInConversation)
                ->where('id', '!=', $userId)
                ->get();
            return response()->json($users);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function deleteParticipante($idConversation, $userId)
    {
        try {
            $currentUserId = Auth::id();

            $conversation = Conversation::findOrFail($idConversation);

            $isAdmin = $conversation->users()
                ->where('users.id', $currentUserId)
                ->wherePivot('role', 'admin')
                ->exists();

            if (!$isAdmin) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $conversation->users()->detach($userId);

            return response()->json([
                'message' => 'Usuario eliminado correctamente'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    public function uploadAvatar(Request $request)
    {
        try {
            $request->validate([
                'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'id_conversation' => 'required|exists:conversations,id'
            ]);

            $path = $request->file('avatar')->store('avatars', 'public');

            $conversation  = Conversation::find($request->input('id_conversation'));

            $conversation->avatar = $path;
            $conversation->save();

            return response()->json([
                'message' => 'Avatar subido correctamente',
                'avatar' => $path
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
