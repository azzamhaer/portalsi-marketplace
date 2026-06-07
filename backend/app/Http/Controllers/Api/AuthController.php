<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => ['required', Password::min(6)],
        ]);
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => $data['password'],
            'role'     => 'BUYER',
        ]);
        $token = $user->createToken('auth')->plainTextToken;
        return response()->json([
            'user' => $this->userResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }
        $token = $user->createToken('auth')->plainTextToken;
        return response()->json([
            'user' => $this->userResource($user->load('vendor')),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        return response()->json($this->userResource($request->user()->load('vendor')));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);
        $request->user()->update($data);
        return response()->json($this->userResource($request->user()->fresh('vendor')));
    }

    private function userResource(User $u): array
    {
        return [
            'id'              => $u->id,
            'name'            => $u->name,
            'email'           => $u->email,
            'phone'           => $u->phone,
            'role'            => $u->role,
            'vendor_id'       => $u->vendor?->id,
            'vendor_username' => $u->vendor?->username,
            'vendor_status'   => $u->vendor?->verification_status,
        ];
    }
}
