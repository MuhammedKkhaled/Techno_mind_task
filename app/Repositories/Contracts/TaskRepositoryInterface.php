<?php

namespace App\Repositories\Contracts;

use App\Models\Task;
use Illuminate\Support\Collection;

interface TaskRepositoryInterface
{
    public function allForUser(int $userId): Collection;

    public function findForUser(int $id, int $userId): Task;

    public function create(array $data): Task;

    public function update(Task $task, array $data): Task;

    public function delete(Task $task): bool;
}
