<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\History\IndexRequest;
use App\Http\Requests\History\ShowRequest;
use App\Http\Resources\HistoryResource;
use App\Services\HistoryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HistoryController extends BaseApiController
{
    public function __construct(public HistoryService $historyService) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $source = (string) $request->route('historySource');
        $recordId = $this->recordId($request, $source);
        $limit = max(1, min(100, (int) $request->integer('limit', 10)));
        $results = $this->historyService->paginate($source, $recordId, $limit);
        $formattedResponse = $this->paginateResponse($results, HistoryResource::class);

        return $this->setStatusMsg($formattedResponse['meta']['total'] ? 'success' : 'failed')
            ->respondOK($formattedResponse, $formattedResponse['meta']['total'] ? __('messages.data_found') : __('messages.data_not_found'));
    }

    public function show(ShowRequest $request): JsonResponse
    {
        $source = (string) $request->route('historySource');
        $recordId = $this->recordId($request, $source);
        $row = $this->historyService->find($source, $recordId, (int) $request->route('history'));
        throw_if($row === null, new NotFoundHttpException(__('messages.data_not_found')));

        return $this->respondOK([
            'data' => new HistoryResource($row),
        ]);
    }

    private function recordId(IndexRequest|ShowRequest $request, string $source): int
    {
        $parameter = (string) config("history.sources.{$source}.parameter");
        $recordId = $this->historyService->resolveRecordId($source, (string) $request->route($parameter));
        throw_if($recordId === null, new NotFoundHttpException(__('messages.data_not_found')));

        return $recordId;
    }
}
