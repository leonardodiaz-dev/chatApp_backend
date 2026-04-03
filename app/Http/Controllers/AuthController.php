<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'lastname' => 'required|string',
                'username' => 'required|string|unique:users,username',
                'email' => 'required|string|email|min:10|max:50|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
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
            $user = User::create([
                'name' => $data['name'],
                'lastname' => $data['lastname'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => bcrypt($data['password'])
            ]);
            return response()->json([
                'Usuario creado con exito',
                $user
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'Error' => $th->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'name' => $user->name,
                'email' => $user->email
            ],
            'token' => $token
        ]);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada'
        ]);
    }
}
