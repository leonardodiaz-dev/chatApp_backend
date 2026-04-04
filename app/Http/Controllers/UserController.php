<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function findUsers($busqueda)
    {
        try {
             $userId = Auth::id();
            $termino = Str::lower($busqueda);
            $resultados = User::whereRaw('LOWER(username) LIKE ?', ["%{$termino}%"])
                ->where('id', '!=', $userId)
                ->get()
                ->map(function($user){
                    return[
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
}
