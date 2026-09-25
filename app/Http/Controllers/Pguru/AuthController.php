<?php

namespace App\Http\Controllers\Pguru;

use App\Services\Pguru\AuthService;
use App\Support\Pguru\AkunApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends PguruController
{
    public function __construct(private AuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $data = $this->validasi($request, [
            'username' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
        ], ['username' => 'Username', 'password' => 'Kata sandi']);

        $user = $this->auth->masuk(trim($data['username']), $data['password'], (string) $request->ip());

        return response()->json([
            'token' => $this->auth->buatToken($user),
            'akun' => AkunApi::dari($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['akun' => AkunApi::dari($this->akun($request))]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->akun($request)->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar']);
    }
}
