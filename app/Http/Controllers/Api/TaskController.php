<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends BaseApiController
{
    public function __construct(private readonly TaskService $tasks)
    {
    }

    public function index(IndexTaskRequest $request): JsonResponse
    {
        $tasks = $this->tasks->list($request->user(), $request->validated());

        return $this->fromResource(TaskResource::collection($tasks))->toResponse();
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->user(), $request->validated());

        return $this->successResponse('Task created successfully.', [
            'task' => new TaskResource($task),
        ], 201);
    }

    public function show(Request $request, int $task): JsonResponse
    {
        $task = $this->tasks->find($request->user(), $task);

        return $this->successResponse('Task retrieved successfully.', [
            'task' => new TaskResource($task),
        ]);
    }

    public function update(UpdateTaskRequest $request, int $task): JsonResponse
    {
        $task = $this->tasks->update($request->user(), $task, $request->validated());

        return $this->successResponse('Task updated successfully.', [
            'task' => new TaskResource($task),
        ]);
    }

    public function destroy(Request $request, int $task): JsonResponse
    {
        $this->tasks->delete($request->user(), $task);

        return $this->successResponse('Task deleted successfully.', []);
    }
}
