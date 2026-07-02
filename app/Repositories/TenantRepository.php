<?php

namespace App\Repositories;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;

class TenantRepository implements TenantRepositoryInterface
{
    public function create(array $data): Tenant
    {
        return Tenant::create($data);
    }
}
