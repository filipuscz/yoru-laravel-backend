<?php

namespace App\Http\Controllers;

use App\Enums\QueryAcceptedComparatorEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class BaseApiController extends Controller
{
    protected int $statusCode = 200;

    protected string $statusMsg = 'success';

    protected int $recordLimit = 25;

    public function __construct() {}

    /**
     * Respond with a JSON response.
     *
     * @param  mixed  $data  The data to be returned.
     * @param  int|null  $status  The HTTP status code.
     * @param  array  $headers  Additional headers to be sent with the response.
     */
    public function respond(mixed $data, mixed $status = null, array $headers = []): JsonResponse
    {
        return Response::json($data, $status ?? $this->statusCode, $headers);
    }

    /**
     * Respond with a JSON response with a message.
     *
     * @param  string  $message  The message to be returned.
     * @param  bool  $success  Indicates if the response is successful.
     * @param  array  $extras  Additional data to be included in the response.
     */
    public function respondDetail(string $message, bool $success = true, array $extras = []): JsonResponse
    {
        $responseArray = [
            'success' => $success,
            'message' => $message,
            'status' => $this->statusMsg,
            'code' => $this->statusCode,
        ];

        if (! empty($extras)) {
            $responseArray = array_merge($responseArray, $extras);
        }

        return $this->respond($responseArray);
    }

    public function setStatusCode(int $statusCode): BaseApiController
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function setStatusMsg(string $message): BaseApiController
    {
        $this->statusMsg = $message;

        return $this;
    }

    public function generateResponse(int $statusCode, ?array $extras = null, ?string $message = '', bool $success = true): JsonResponse
    {
        return $this->setStatusCode($statusCode)->respondDetail($message ?? '', $success, $extras ?? []);
    }

    public function respondOK(?array $extras = null, ?string $message = null, bool $success = true): JsonResponse
    {
        return $this->generateResponse(200, $extras, $message ?? __('messages.success'), $success);
    }

    public function respondCreated(?array $extras = null, ?string $message = null, bool $success = true): JsonResponse
    {
        return $this->generateResponse(201, $extras, $message ?? __('messages.created'), $success);
    }

    public function respondDeleted(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->generateResponse(204, $extras, $message ?? __('messages.deleted'), $success);
    }

    public function respondRedirect(string $url, int $status = 302, array $headers = []): JsonResponse
    {
        return $this->generateResponse($status, ['redirect_uri' => $url], null, true)->withHeaders($headers);
    }

    public function respondBadRequest(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->setStatusMsg('failed')->generateResponse(400, $extras, $message ?? __('messages.bad_request'), $success);
    }

    public function respondUnauthorized(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->setStatusMsg('failed')->generateResponse(401, $extras, $message ?? __('messages.unauthorized'), $success);
    }

    public function respondForbidden(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->setStatusMsg('failed')->generateResponse(403, $extras, $message ?? __('messages.forbidden'), $success);
    }

    public function respondNotFound(?array $extras = null, ?string $message = null, bool $success = true): JsonResponse
    {
        return $this->setStatusMsg('failed')->generateResponse(404, $extras, $message ?? __('messages.not_found'), $success);
    }

    public function respondConflict(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->generateResponse(409, $extras, $message ?? __('messages.conflict'), $success);
    }

    public function respondInternalError(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->setStatusMsg('failed')->generateResponse(500, $extras, $message ?? __('messages.internal_error'), $success);
    }

    public function respondUnprocessableEntity(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->generateResponse(422, $extras, $message ?? __('messages.unprocessable_entity'), $success);
    }

    public function respondNotAcceptable(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->generateResponse(406, $extras, $message ?? __('messages.not_acceptable'), $success);
    }

    public function respondTooManyRequests(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->generateResponse(429, $extras, $message ?? __('messages.too_many_requests'), $success);
    }

    public function respondNotImplemented(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->generateResponse(501, $extras, $message ?? __('messages.not_implemented'), $success);
    }

    public function respondServiceUnavailable(?array $extras = null, ?string $message = null, bool $success = false): JsonResponse
    {
        return $this->generateResponse(503, $extras, $message ?? __('messages.service_unavailable'), $success);
    }

    /**
     * Paginate the response data.
     *
     * @param  LengthAwarePaginator|Collection  $paginatedData  The paginated data.
     * @param  string|null  $resource  The resource class name for transformation.
     * @return array The paginated response data.
     */
    public function paginateResponse(LengthAwarePaginator|CursorPaginator|Collection $paginatedData, mixed $resource = null): array
    {
        if ($paginatedData instanceof LengthAwarePaginator) {
            if (is_subclass_of($resource, JsonResource::class)) {
                $items = $resource::collection($paginatedData->items());
            } else {
                $items = $paginatedData->items();
            }

            return [
                'data' => $items,
                'meta' => [
                    'total' => $paginatedData->total(),
                    'per_page' => $paginatedData->perPage(),
                    'current_page' => $paginatedData->currentPage(),
                    'last_page' => $paginatedData->lastPage(),
                    'total_page' => ceil($paginatedData->total() / $paginatedData->perPage()),
                ],
                'links' => [
                    'first' => $paginatedData->url(1),
                    'last' => $paginatedData->url($paginatedData->lastPage()),
                    'prev' => $paginatedData->previousPageUrl(),
                    'next' => $paginatedData->nextPageUrl(),
                ],
            ];
        }

        if ($paginatedData instanceof CursorPaginator) {
            if (is_subclass_of($resource, JsonResource::class)) {
                $items = $resource::collection($paginatedData->items());
            } else {
                $items = $paginatedData->items();
            }

            return [
                'data' => $items,
                'meta' => [
                    'per_page' => $paginatedData->perPage(),
                    'has_more_pages' => $paginatedData->hasMorePages(),
                    'next_cursor' => $paginatedData->nextCursor()?->encode(),
                    'prev_cursor' => $paginatedData->previousCursor()?->encode(),
                ],
                'links' => [
                    'first' => $paginatedData->url(null),
                    'prev' => $paginatedData->previousPageUrl(),
                    'next' => $paginatedData->nextPageUrl(),
                ],
            ];
        }

        // Case when the data is not paginated / limit is -1
        if (is_subclass_of($resource, JsonResource::class)) {
            $items = $resource::collection($paginatedData->all());
        } else {
            $items = $paginatedData->all();
        }

        return [
            'data' => $items,
            'meta' => [
                'total' => $paginatedData->count(),
                'limit' => $paginatedData->count(),
                'last_page' => 1,
                'total_page' => 1,
            ],
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
        ];
    }

    public function formatErrors(array $errors): array
    {
        $bag = [];

        foreach ($errors as $value) {
            $key = explode(' ', $value)[0];
            $bag[] = [
                'name' => $key,
                'message' => $value,
            ];
        }

        return $bag;
    }

    /**
     * Prepare the $request for findByIndexes method.
     *
     * @param  array  $indexes  The indexes array.
     * @return array The prepared indexes.
     */
    public function prepareIndexes(array $indexes): array
    {
        $filters = $indexes['filters'] ?? [];
        $orderByColumns = $indexes['orderByColumns'] ?? [];
        $orderBy = $orderByColumns ? explode(',', $orderByColumns) : [];
        $any = $indexes['any'] ?? false;
        $any = filter_var($any, FILTER_VALIDATE_BOOLEAN);
        $limit = $indexes['limit'] ?? 10;
        $defaultComparator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $comparator = $indexes['comparator'] ?? $defaultComparator;
        $qcomparator = QueryAcceptedComparatorEnum::tryFrom($comparator) ?? QueryAcceptedComparatorEnum::EQUAL;

        if (isset($indexes['ignoreIds'])) {
            $indexes['ignore'] = explode(',', $indexes['ignoreIds']);
            unset($indexes['ignoreIds']);
        }

        $useCursor = $indexes['use_cursor'] ?? false;
        $useCursor = filter_var($useCursor, FILTER_VALIDATE_BOOLEAN);
        $indexes['useCursor'] = $useCursor; // inject back into indexes array for base service

        return [
            'indexes' => $indexes,
            'any' => $any,
            'limit' => $limit,
            'orderBy' => $orderBy,
            'qcomparator' => $qcomparator,
            'filters' => $filters,
            'useCursor' => $useCursor,
        ];
    }
}
