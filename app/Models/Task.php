<?php

namespace App\Models;

use App\Services\Cache\CacheService;
use App\TaskStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToPrimaryModel;

class Task extends Model
{
    use HasFactory;
    use BelongsToPrimaryModel;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatusEnum::class,
            'due_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($task) {
            CacheService::cacheTask($task);
        });

        static::deleted(function ($task) {
            CacheService::cacheTask($task);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRelationshipToPrimaryModel(): string
    {
        return 'user';
    }
}
