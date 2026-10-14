<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\BaseApiController;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function __construct(public NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 15);
        $notifications = $this->notificationService->index($request->user(), $limit);

        $formattedResponse = $this->paginateResponse($notifications, NotificationResource::class);

        return $this->respondOK($formattedResponse, __('messages.data_found'));
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $this->notificationService->markAsRead($request->user(), $notification);

        return $this->respondOK(null, __('messages.resources_updated'));
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user());

        return $this->respondOK(null, __('messages.resources_updated'));
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->notificationService->delete($request->user(), $notification);

        return $this->respondOK(null, __('messages.resources_deleted'));
    }
}
