<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
    $request->merge(['email' => strtolower(trim((string) $request->email))]);

    $dados = $request->validate([
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'curso'    => 'nullable|string|max:255',
    ], [
        'name.required'      => 'Informe seu nome.',
        'email.required'     => 'Informe seu e-mail.',
        'email.email'        => 'Esse e-mail não parece válido.',
        'email.unique'       => 'Esse e-mail já está cadastrado. Tente entrar.',
        'password.required'  => 'Informe uma senha.',
        'password.min'       => 'A senha precisa ter pelo menos 8 caracteres.',
        'password.confirmed' => 'As senhas não são iguais.',
    ]);

    $user  = User::create($dados);
    $token = $user->createToken('estudaflow')->plainTextToken;

    return response()->json(['user' => $user, 'token' => $token], 201);
    }

    
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email ou senha incorretos.'],
            ]);
        }

        $token = $user->createToken('estudaflow')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}