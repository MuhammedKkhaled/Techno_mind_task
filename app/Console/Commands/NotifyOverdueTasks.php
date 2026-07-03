<?php

namespace App\Console\Commands;

use App\Mail\TaskOverdueMail;
use App\Models\Task;
use App\TaskStatusEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyOverdueTasks extends Command
{
    protected $signature = 'tasks:notify-overdue';

    protected $description = 'Queue an email for every task whose due_date has passed and mark it as notified';

    public function handle(): int
    {
        $notified = 0;
        $failed = 0;

        Task::withoutParentModel()
            ->with('user')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->where('status', '!=', TaskStatusEnum::DONE->value)
            ->whereNull('due_date_notified_at')
            ->chunkById(200, function ($tasks) use (&$notified, &$failed) {
                $notifiedIds = [];

                foreach ($tasks as $task) {
                    try {
                        Mail::to($task->user)->queue(new TaskOverdueMail($task));
                        $notifiedIds[] = $task->id;
                    } catch (Throwable $e) {
                        $failed++;
                        Log::error('Failed to queue overdue task notification', [
                            'task_id' => $task->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($notifiedIds !== []) {
                    Task::withoutParentModel()
                        ->whereIn('id', $notifiedIds)
                        ->update(['due_date_notified_at' => now()]);

                    $notified += count($notifiedIds);
                }
            });

        $this->info("Queued {$notified} overdue task notification(s), {$failed} failed to queue.");

        return self::SUCCESS;
    }
}
