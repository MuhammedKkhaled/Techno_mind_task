<?php

namespace App\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\Response;

class BaseApiController extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    private array $response = [];

    private int $statusCode = Response::HTTP_OK;

    public function addToResponse($data): static
    {
        $this->response += $data;

        return $this;
    }

    public function addMessageToResponse(string $message): static
    {
        $this->response += [
            'message' => $message,
        ];

        return $this;
    }

    public function fromResource(JsonResource $resource): self
    {
        $this->addToResponse([$resource::$wrap => $resource]);
        if ($resource->resource instanceof LengthAwarePaginator) {

            $this->addToResponse($this->getPaginationResponse($resource->resource));
        }

        return $this;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getPaginationResponse(LengthAwarePaginator $resource): array
    {
        return [
            'meta' => [
                'total_Items' => $resource->total(),
                'items_Per_Page' => $resource->perPage(),
                'Items_in_page' => $resource->count(),
                'current_Page' => $resource->currentPage(),
                'last_Page' => $resource->lastPage(),
                'next_pageUrl' => $resource->nextPageUrl(),
                'previous_pageUrl' => $resource->previousPageUrl(),
            ],
        ];
    }

    public function toResponse(): JsonResponse
    {
        return response()->json($this->response, $this->statusCode);
    }

    public function successResponse(string $message, array $data, int $statusCode = 200): JsonResponse
    {
        return $this
            ->setStatusCode($statusCode)
            ->addMessageToResponse($message)
            ->addToResponse($data)
            ->toResponse();
    }

    public function errorResponse(string $message, $error = null, int $statusCode = 500): JsonResponse
    {
        $response = ['error' => $error];

        return $this
            ->setStatusCode($statusCode)
            ->addMessageToResponse($message)
            ->addToResponse($response)
            ->toResponse();
    }
}
