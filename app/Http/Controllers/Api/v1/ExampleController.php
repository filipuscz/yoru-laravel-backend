<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\Example\BulkDestroyRequest;
use App\Http\Requests\Example\BulkUpdateRequest;
use App\Http\Requests\Example\DestroyRequest;
use App\Http\Requests\Example\IndexRequest;
use App\Http\Requests\Example\ShowRequest;
use App\Http\Requests\Example\StoreUpdateRequest;
use App\Http\Resources\ExampleResource;
use App\Services\ExampleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExampleController extends BaseApiController
{
    public function __construct(public ExampleService $exampleService) {}

    /**
     * Display a listing of the resource.
     *
     * @response array{success: string, message: string, status: string, code: integer, data: collection,
     * meta: array{}, links: array{}}
     */
    public function index(IndexRequest $request): JsonResponse
    {
        // getPrintableColumns
        $indexes = $this->prepareIndexes($request->all());
        $results = $this->exampleService->findByIndexes(
            $indexes['indexes'],
            $indexes['any'],
            $indexes['limit'],
            $indexes['orderBy'],
            $indexes['qcomparator'],
            $indexes['filters']
        );
        $formattedResponse = $this->paginateResponse($results, ExampleResource::class);

        return $this->setStatusMsg($formattedResponse['meta']['total'] ? 'success' : 'failed')
            ->respondOK($formattedResponse, $formattedResponse['meta']['total'] ? __('messages.data_found') : __('messages.data_not_found'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @response array{success: string, message: string, status: string, code: integer, data: array{}}
     */
    public function store(StoreUpdateRequest $request): JsonResponse
    {
        $data = $this->exampleService->create($request->all());

        return $this->respondOK([
            'data' => new ExampleResource($data),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @response array{success: string, message: string, status: string, code: integer, data: array{}}
     */
    public function show(ShowRequest $request, string $example): JsonResponse
    {
        $data = $this->exampleService->findById($example);
        throw_if((empty($data)), new NotFoundHttpException(__('messages.data_not_found')));

        return $this->setStatusMsg('success')->respondOK([
            'data' => new ExampleResource($data),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $idOrSlug) {}

    /**
     * Update the specified resource in storage.
     *
     * @response array{success: string, message: string, status: string, code: integer, data: array{}}
     */
    public function update(StoreUpdateRequest $request, string $example): JsonResponse
    {
        $data = $this->exampleService->update($request->all(), $example);

        return $this->respondOK([
            'data' => new ExampleResource($data),
        ]);
    }

    /**
     * Update multiple resources in storage.
     *
     * @response array{success: string, message: string, status: string, code: integer}
     */
    public function bulkUpdate(BulkUpdateRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        // except ids
        $updateData = $request->except('ids');
        if (empty($ids)) {
            throw new NotFoundHttpException(__('exceptions.no_ids_for_update'));
        }
        if (empty($updateData)) {
            throw new NotFoundHttpException(__('exceptions.no_data_for_update'));
        }
        $count = $this->exampleService->updateMany($ids, $updateData);

        return $this->respondOK(null, __('messages.resources_updated', ['count' => $count]), true);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @response array{success: string, message: string, status: string, code: integer}
     */
    public function destroy(DestroyRequest $request, string $example): JsonResponse
    {
        $data = $this->exampleService->findById($example);
        throw_if(! $data, new NotFoundHttpException(__('messages.data_not_found')));

        $this->exampleService->delete($example);

        return $this->respondOK(null, __('messages.deleted'), true);
    }

    /**
     * Remove multiple resources from storage.
     *
     * @response array{success: string, message: string, status: string, code: integer}
     */
    public function bulkDestroy(BulkDestroyRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            throw new NotFoundHttpException(__('exceptions.no_ids_for_deletion'));
        }
        $count = $this->exampleService->deleteMany($ids);

        return $this->respondOK(null, __('messages.resources_deleted', ['count' => $count]), true);
    }
}
