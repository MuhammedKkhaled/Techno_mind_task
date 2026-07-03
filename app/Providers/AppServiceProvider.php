<?php

namespace App\Providers;

use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\TaskRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Contracts\UserNotifierInterface;
use App\Services\UserNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
        $this->app->bind(UserNotifierInterface::class, UserNotifier::class);
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::whenQueryingForLongerThan(500, function (Connection $connection, QueryExecuted $query) {
            logger()->debug('Slow Query Detected', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
            ]);
        });

        \Illuminate\Support\Facades\RateLimiter::for('api', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Disable lazy loading only when outside of production
        Model::preventLazyLoading(! $this->app->isProduction());

    }
}
