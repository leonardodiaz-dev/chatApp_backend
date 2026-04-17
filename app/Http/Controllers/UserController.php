<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function findUsers($busqueda)
    {
        try {
            $userId = Auth::id();
            $termino = Str::lower($busqueda);

            $contactIds = User::whereHas('conversations', function ($q) use ($userId) {
                $q->whereHas('users', fn($query) => $query->where('users.id', $userId));
            })->where('id', '!=', $userId)
                ->get()
                ->pluck('id');

            $resultados = User::whereRaw('LOWER(username) LIKE ?', ["%{$termino}%"])
                ->where('id', '!=', $userId)
                ->whereNotIn('id',$contactIds)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'lastname' => $user->lastname,
                    ];
                });

            return response()->json($resultados);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'Error' => $th->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {

            $userId = Auth::id();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validacion invalidos',
                    'error' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $user = User::findOrFail($userId);

            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $imagePath = $request->file('avatar')->store('users', 'public');

                $user->avatar = $imagePath;
            }

            $user->name = $data['name'];

            $user->save();

            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar
            ]);
        } catch (\Throwable $th) {

            if (isset($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'Error' => $th->getMessage()
            ], 500);
        }
    }
}
