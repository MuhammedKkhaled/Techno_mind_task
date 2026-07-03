<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserNotifierInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TenantRepositoryInterface $tenants,
        private readonly UserNotifierInterface $notifier,
    ) {
    }

    public function register(array $data): array
    {
        $tenant = $this->tenants->create([
            'name' => "{$data['name']}'s Organization",
        ]);

        $user = $this->users->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->notifier->sendOnboardingEmail($user);

        return $this->issueTokens($user);
    }

    public function login(array $credentials): array
    {
        $user = $this->users->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->issueTokens($user);
    }

    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    private function issueTokens(User $user): array
    {
        $accessToken = $user->createToken(
            'access_token',
            ['access-api'],
            now()->addMinutes(config('sanctum.expiration')),
        );

        $refreshToken = $user->createToken(
            'refresh_token',
            ['issue-access-token'],
            now()->addMinutes(config('sanctum.refresh_expiration')),
        );

        return [
            'user' => $user,
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
        ];
    }
}
