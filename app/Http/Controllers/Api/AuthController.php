<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseApiController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->successResponse('Registered successfully.', $this->tokenData($result), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->successResponse('Logged in successfully.', $this->tokenData($result));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse('Logged out successfully.', []);
    }

    private function tokenData(array $result): array
    {
        return [
            'user' => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => 'Bearer',
        ];
    }
}
