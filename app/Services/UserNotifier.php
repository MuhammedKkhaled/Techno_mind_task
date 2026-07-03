<?php

namespace App\Services;

use App\Mail\OnboardingMail;
use App\Models\User;
use App\Services\Contracts\UserNotifierInterface;
use Illuminate\Support\Facades\Mail;

class UserNotifier implements UserNotifierInterface
{
    public function sendOnboardingEmail(User $user): void
    {
        Mail::to($user)->queue(new OnboardingMail($user));
    }
}
