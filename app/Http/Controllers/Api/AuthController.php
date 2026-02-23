<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Autentica al usuario y devuelve un token Sanctum para la API.
     * Solo permite el acceso a usuarios activos.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->email)->first();

        // Verificar que el usuario existe, la contraseña es correcta y está activo
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas no son correctas.',
            ], 401);
        }

        if (! $user->active) {
            return response()->json([
                'message' => 'Tu cuenta está inactiva. Contacta al administrador.',
            ], 401);
        }

        // Crear token Sanctum para el dispositivo POS
        $token = $user->createToken('pos-device')->plainTextToken;

        // Cargar roles y permisos
        $user->load('roles.permissions');

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * Cierra la sesión del usuario revocando el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
