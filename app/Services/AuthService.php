<?php

namespace App\Services;

use App\Mail\OnboardingMail;
use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TenantRepositoryInterface $tenants,
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

        Mail::to($user)->queue(new OnboardingMail($user));

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
