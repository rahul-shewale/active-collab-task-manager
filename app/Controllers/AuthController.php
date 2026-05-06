<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

class AuthController
{
    public function login(Request $request): void
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = Auth::attempt($data['email'], $data['password']);
        if (!$user) {
            Response::json(['errors' => ['email' => ['The provided credentials are incorrect.']]], 422);
        }

        $token = Auth::createToken($user['id']);

        Response::json([
            'token' => $token,
            'user'  => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);
    }

    public function logout(Request $request): void
    {
        Auth::guard($request);
        $token = $request->bearerToken();
        Auth::revokeToken($token);
        Response::json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): void
    {
        $user = Auth::guard($request);
        unset($user['password'], $user['remember_token']);
        Response::json($user);
    }
}
