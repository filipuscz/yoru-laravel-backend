<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\User\BulkDestroyRequest;
use App\Http\Requests\User\BulkUpdateRequest;
use App\Http\Requests\User\DestroyRequest;
use App\Http\Requests\User\IndexRequest;
use App\Http\Requests\User\ShowRequest;
use App\Http\Requests\User\StoreUpdateRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends BaseApiController
{
    public function __construct(public UserService $userService) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $indexes = $this->prepareIndexes($request->all());
        $results = $this->userService->findByIndexes(
            $indexes['indexes'],
            $indexes['any'],
            $indexes['limit'],
            $indexes['orderBy'],
            $indexes['qcomparator'],
            $indexes['filters']
        );

        if ($results instanceof LengthAwarePaginator) {
            /** @var Collection $collection */
            $collection = $results->getCollection();
            $collection->load(['roles', 'detail']);
        } else {
            /** @var Collection $results */
            $results->load(['roles', 'detail']);
        }

        $formattedResponse = $this->paginateResponse($results, UserResource::class);

        return $this->setStatusMsg($formattedResponse['meta']['total'] ? 'success' : 'failed')
            ->respondOK($formattedResponse, $formattedResponse['meta']['total'] ? __('messages.data_found') : __('messages.data_not_found'));
    }

    public function store(StoreUpdateRequest $request): JsonResponse
    {
        $data = $this->userService->create($request->all());

        return $this->respondOK([
            'data' => new UserResource($data),
        ]);
    }

    public function show(ShowRequest $request, string $user): JsonResponse
    {
        $data = $this->userService->findById($user);
        throw_if((empty($data)), new NotFoundHttpException(__('messages.data_not_found')));

        return $this->setStatusMsg('success')->respondOK([
            'data' => new UserResource($data),
        ]);
    }

    public function update(StoreUpdateRequest $request, string $user): JsonResponse
    {
        $data = $this->userService->update($request->all(), $user);

        return $this->respondOK([
            'data' => new UserResource($data),
        ]);
    }

    public function bulkUpdate(BulkUpdateRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $updateData = $request->except('ids');
        if (empty($ids)) {
            throw new NotFoundHttpException(__('exceptions.no_ids_for_update'));
        }
        if (empty($updateData)) {
            throw new NotFoundHttpException(__('exceptions.no_data_for_update'));
        }
        $count = $this->userService->updateMany($ids, $updateData);

        return $this->respondOK(null, __('messages.resources_updated', ['count' => $count]), true);
    }

    public function destroy(DestroyRequest $request, string $user): JsonResponse
    {
        $data = $this->userService->findById($user);
        throw_if(! $data, new NotFoundHttpException(__('messages.data_not_found')));

        $this->userService->delete($user);

        return $this->respondOK(null, __('messages.deleted'), true);
    }

    public function bulkDestroy(BulkDestroyRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            throw new NotFoundHttpException(__('exceptions.no_ids_for_deletion'));
        }
        $count = $this->userService->deleteMany($ids);

        return $this->respondOK(null, __('messages.resources_deleted', ['count' => $count]), true);
    }
}
