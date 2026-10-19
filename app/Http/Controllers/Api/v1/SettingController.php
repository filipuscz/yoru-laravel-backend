<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\Setting\BulkUpdateRequest;
use App\Http\Requests\Setting\IndexRequest;
use App\Http\Resources\SettingResource;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends BaseApiController
{
    public function __construct(public SettingService $settingService) {}

    public function index(IndexRequest $request): JsonResponse
    {
        return $this->respondWithIndex($request, $this->settingService, SettingResource::class);
    }

    public function bulkUpdate(BulkUpdateRequest $request): JsonResponse
    {
        $settings = $request->input('settings', []);
        $this->settingService->setMany($settings);

        return $this->respondOK(null, __('messages.resources_updated', ['count' => count($settings)]), true);
    }
}
