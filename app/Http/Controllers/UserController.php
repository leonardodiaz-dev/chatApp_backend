<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function findUsers($busqueda)
    {
        try {
            $termino = Str::lower($busqueda);
            $resultados = User::whereRaw('LOWER(username) LIKE ?', ["%{$termino}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$termino}%"])
                ->get()
                ->map(function($user){
                    return[
                        'name' => $user->name,
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
