<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\BaseBulkDestroyRequest;
use App\Http\Requests\BaseBulkUpdateRequest;
use App\Http\Requests\BaseIndexRequest;
use App\Http\Requests\Role\StoreUpdateRequest;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RoleController extends BaseApiController
{
    public function __construct(public RoleService $roleService) {}

    public function index(BaseIndexRequest $request): JsonResponse
    {
        return $this->respondWithIndex(
            $request,
            $this->roleService,
            RoleResource::class,
            ['permissions'],
        );
    }

    public function store(StoreUpdateRequest $request): JsonResponse
    {
        $data = $this->roleService->create($request->all());

        return $this->respondOK([
            'data' => new RoleResource($data),
        ]);
    }

    public function show(string $role): JsonResponse
    {
        $data = $this->roleService->findById($role);
        throw_if((empty($data)), new NotFoundHttpException(__('messages.data_not_found')));

        return $this->setStatusMsg('success')->respondOK([
            'data' => new RoleResource($data),
        ]);
    }

    public function update(StoreUpdateRequest $request, string $role): JsonResponse
    {
        $data = $this->roleService->update($request->all(), $role);

        return $this->respondOK([
            'data' => new RoleResource($data),
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
        $count = $this->roleService->updateMany($ids, $updateData);

        return $this->respondOK(null, __('messages.resources_updated', ['count' => $count]), true);
    }

    public function destroy(string $role): JsonResponse
    {
        $data = $this->roleService->findById($role);
        throw_if(! $data, new NotFoundHttpException(__('messages.data_not_found')));

        $this->roleService->delete($role);

        return $this->respondOK(null, __('messages.deleted'), true);
    }

    public function bulkDestroy(BaseBulkDestroyRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            throw new NotFoundHttpException(__('exceptions.no_ids_for_deletion'));
        }
        $count = $this->roleService->deleteMany($ids);

        return $this->respondOK(null, __('messages.resources_deleted', ['count' => $count]), true);
    }
}
