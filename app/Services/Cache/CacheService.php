<?php

namespace App\Services\Cache;

use App\Models\Task;
use Closure;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    public const CACHE_TTL = 3600 * 24; // 1 day

    public const TASKS_PREFIX = 'tasks_user_';

    public static function get(string $key, ?callable $callback = null): mixed
    {
        return Cache::get($key, $callback ?? fn () => collect());
    }

    public static function forget(string $key): void
    {
        Cache::forget($key);
    }

    public static function rememberForever(string $key, callable $callback): mixed
    {
        return Cache::rememberForever($key, Closure::fromCallable($callback));
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, Closure::fromCallable($callback));
    }

    public static function taskKey(int $userId): string
    {
        return self::TASKS_PREFIX . $userId;
    }

    
    public static function cacheTask(Task $task): void
    {
        $tasks = Task::query()
            ->where('user_id', $task->user_id)
            ->get();

        $key = self::taskKey($task->user_id);

        self::forget($key);
        self::rememberForever($key, fn () => $tasks);
    }
}
