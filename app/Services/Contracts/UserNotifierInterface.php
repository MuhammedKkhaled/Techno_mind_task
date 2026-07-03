<?php

namespace App\Services\Contracts;

use App\Models\User;

interface UserNotifierInterface
{
    public function sendOnboardingEmail(User $user): void;
}
