<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Cache\CacheService;
use App\TaskStatusEnum;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskService
{
    public function __construct(private readonly TaskRepositoryInterface $tasks)
    {
    }

    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $tasks = CacheService::remember(
            CacheService::taskKey($user->id),
            CacheService::CACHE_TTL,
            fn () => $this->tasks->allForUser($user->id),
        );

        if (! empty($filters['status'])) {
            $status = TaskStatusEnum::from($filters['status']);
            $tasks = $tasks->where('status', $status);
        }

        if (! empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $tasks = $tasks->filter(
                fn (Task $task) => str_contains(mb_strtolower($task->title), $search),
            );
        }

        return $this->paginate($tasks->values(), (int) ($filters['per_page'] ?? 15));
    }

    public function find(User $user, int $id): Task
    {
        return $this->tasks->findForUser($id, $user->id);
    }

    public function create(User $user, array $data): Task
    {
        return $this->tasks->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function update(User $user, int $id, array $data): Task
    {
        $task = $this->find($user, $id);

        return $this->tasks->update($task, $data);
    }

    public function delete(User $user, int $id): void
    {
        $task = $this->find($user, $id);

        $this->tasks->delete($task);
    }

    private function paginate(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }
}
