<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
