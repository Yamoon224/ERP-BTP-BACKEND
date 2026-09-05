<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Requests\LoginRequest;
use App\Domains\Auth\Http\Resources\UserResource;
use App\Domains\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->attempt(
            $request->only('email', 'password'),
            $request->input('device_name', 'api'),
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(null, 204);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
