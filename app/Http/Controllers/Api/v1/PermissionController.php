<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\BaseBulkDestroyRequest;
use App\Http\Requests\BaseBulkUpdateRequest;
use App\Http\Requests\BaseIndexRequest;
use App\Http\Requests\Permission\StoreUpdateRequest;
use App\Http\Resources\PermissionResource;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PermissionController extends BaseApiController
{
    public function __construct(public PermissionService $permissionService) {}

    public function index(BaseIndexRequest $request): JsonResponse
    {
        $indexes = $this->prepareIndexes($request->all());
        $results = $this->permissionService->findByIndexes(
            $indexes['indexes'],
            $indexes['any'],
            $indexes['limit'],
            $indexes['orderBy'],
            $indexes['qcomparator'],
            $indexes['filters']
        );

        $formattedResponse = $this->paginateResponse($results, PermissionResource::class);

        return $this->setStatusMsg($formattedResponse['meta']['total'] ? 'success' : 'failed')
            ->respondOK($formattedResponse, $formattedResponse['meta']['total'] ? __('messages.data_found') : __('messages.data_not_found'));
    }

    public function store(StoreUpdateRequest $request): JsonResponse
    {
        $data = $this->permissionService->create($request->all());

        return $this->respondOK([
            'data' => new PermissionResource($data),
        ]);
    }

    public function show(string $permission): JsonResponse
    {
        $data = $this->permissionService->findById($permission);
        throw_if((empty($data)), new NotFoundHttpException(__('messages.data_not_found')));

        return $this->setStatusMsg('success')->respondOK([
            'data' => new PermissionResource($data),
        ]);
    }

    public function update(StoreUpdateRequest $request, string $permission): JsonResponse
    {
        $data = $this->permissionService->update($request->all(), $permission);

        return $this->respondOK([
            'data' => new PermissionResource($data),
        ]);
    }

    public function bulkUpdate(BaseBulkUpdateRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $updateData = $request->except('ids');
        if (empty($ids)) {
            throw new NotFoundHttpException(__('exceptions.no_ids_for_update'));
        }
        if (empty($updateData)) {
            throw new NotFoundHttpException(__('exceptions.no_data_for_update'));
        }
        $count = $this->permissionService->updateMany($ids, $updateData);

        return $this->respondOK(null, __('messages.resources_updated', ['count' => $count]), true);
    }

    public function destroy(string $permission): JsonResponse
    {
        $data = $this->permissionService->findById($permission);
        throw_if(! $data, new NotFoundHttpException(__('messages.data_not_found')));

        $this->permissionService->delete($permission);

        return $this->respondOK(null, __('messages.deleted'), true);
    }

    public function bulkDestroy(BaseBulkDestroyRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            throw new NotFoundHttpException(__('exceptions.no_ids_for_deletion'));
        }
        $count = $this->permissionService->deleteMany($ids);

        return $this->respondOK(null, __('messages.resources_deleted', ['count' => $count]), true);
    }
}
