<?php

namespace App\Repositories;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Support\Collection;

class TaskRepository implements TaskRepositoryInterface
{
    public function allForUser(int $userId): Collection
    {
        return Task::with('user')
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function findForUser(int $id, int $userId): Task
    {
        return Task::with('user')
            ->where('user_id', $userId)
            ->findOrFail($id);
    }

    public function create(array $data): Task
    {
        return Task::create($data);
    }

    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task;
    }

    public function delete(Task $task): bool
    {
        return (bool) $task->delete();
    }
}
