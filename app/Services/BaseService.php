<?php

namespace App\Services;

use App\Contracts\IBaseService;
use App\Enums\QueryAcceptedComparatorEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Class BaseService
 *
 * A base service class implementing common CRUD operations.
 */
class BaseService implements IBaseService
{
    /** @var Model The model associated with this service. */
    protected Model $model;

    protected string $moduleName = 'base';

    /** @var int Size of queued import rows */
    protected int $chunkSize = 1000;

    /** @var array The columns that can be exported. */
    protected $printableColumns = [];

    /** @var array|null The cached columns of the table. */
    protected ?array $tableColumns = null;

    /**
     * Constructor.
     *
     * @param  Model  $model  The Eloquent model associated with this service.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get the columns of the table.
     *
     * @return array The columns of the table.
     */
    protected function getTableColumns(): array
    {
        if ($this->tableColumns === null) {
            $this->tableColumns = $this->model->getConnection()->getSchemaBuilder()->getColumnListing($this->model->getTable());
        }

        return $this->tableColumns;
    }

    /**
     * Case-insensitive match on PostgreSQL. Non-text columns are cast so ILIKE does not fail.
     */
    protected function wherePattern(Builder $query, string $column, string $pattern, bool $or = false): void
    {
        if ($query->getQuery()->getGrammar() instanceof PostgresGrammar) {
            $wrapped = $query->getGrammar()->wrap($column);
            $sql = "CAST({$wrapped} AS TEXT) ILIKE ?";

            if ($or) {
                $query->orWhereRaw($sql, [$pattern]);
            } else {
                $query->whereRaw($sql, [$pattern]);
            }

            return;
        }

        if ($or) {
            $query->orWhere($column, QueryAcceptedComparatorEnum::LIKE->value, $pattern);
        } else {
            $query->where($column, QueryAcceptedComparatorEnum::LIKE->value, $pattern);
        }
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the module name associated with this service.
     *
     * @return string The module name.
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Get the columns that can be exported.
     *
     * @return array The columns that can be exported.
     */
    public function getPrintableColumns(): array
    {
        return $this->printableColumns;
    }

    /**
     * Set the columns that can be exported.
     *
     * @param  array  $columns  The columns that can be exported.
     */
    public function setPrintableColumns(array $columns = ['*']): self
    {
        if (in_array('*', $columns)) {
            $this->printableColumns = $this->getTableColumns();
        } else {
            $this->printableColumns = $columns;
        }

        // remove guarded attributes from printable columns
        $this->printableColumns = array_diff($this->printableColumns, $this->model->getGuarded());

        return $this;
    }

    /**
     * Create a new model instance and persist it to the database.
     *
     * @param  array  $attr  The attributes for the new model instance.
     * @return Model The created model instance.
     *
     * @throws \Exception If unable to save the model.
     */
    public function create(array $attr): Model
    {
        return DB::transaction(function () use ($attr) {
            $modelName = get_class($this->model);

            $data = $this->model->newInstance($attr);

            // Save the new data to the database within the transaction
            if ($data->save()) {
                return $data;
            }

            throw new \Exception(__('exceptions.failed_to_save', ['model' => $modelName]));
        });
    }

    /**
     * Create multiple model instances and persist them to the database.
     *
     * @param  array  $data  An array of attributes for the new model instances.
     * @return array An array of created model instances.
     */
    public function createMany(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        return DB::transaction(function () use ($data) {
            $storedData = [];
            foreach ($data as $key => $item) {
                try {
                    $model = $this->model->newInstance($item);
                    $model->save();
                    $storedData[] = $model->refresh();
                } catch (\Throwable $th) {
                    Log::error($th->getMessage());
                }
            }

            return $storedData;
        });
    }

    /**
     * Find a model by its ID or slug.
     *
     * @param  mixed  $idOrSlug  The ID or slug of the model to find.
     * @return Model|null The found model instance, or null if not found.
     */
    public function findById(mixed $idOrSlug): ?Model
    {
        $query = $this->model->newQuery();
        $data = $query->find($idOrSlug);

        if (! $data && ! is_numeric($idOrSlug) && in_array('slug', $this->getTableColumns())) {
            $data = $query->where('slug', $idOrSlug)->first();
        }

        return $data;
    }

    /**
     * Find model instances based on specified indexes.
     *
     * @param  array  $indexes  An array of column-value pairs for filtering.
     * @param  bool  $any  Whether to match any or all of the provided indexes.
     * @param  int|null  $limit  The maximum number of results to return.
     * @param  array  $orderBy  An array of columns to order the results by.
     * @param  QueryAcceptedComparatorEnum  $comparator  The comparison operator for each index.
     * @param  array|null  $relation  Optional related models to eager load.
     * @param  array|null  $fields  Optional columns to search for a specific value.
     * @return Collection|LengthAwarePaginator|CursorPaginator|Builder The matching model instances.
     */
    public function findByIndexes(
        array $indexes,
        bool $any,
        ?int $limit,
        array $orderBy,
        QueryAcceptedComparatorEnum $comparator = QueryAcceptedComparatorEnum::EQUAL,
        ?array $filters = null,
        ?array $fields = null,
        ?array $relation = null,
        ?array $defaultOrderBy = null,
    ): Collection|LengthAwarePaginator|CursorPaginator|Builder {
        // dd($this->model);
        // Get column names of the model's table
        $columns = $this->getTableColumns();
        $query = $this->model->newQuery();
        // dd($any, $indexes, $limit, $orderBy, $comparator, $relation);

        // Apply filters if provided
        if (! empty($filters)) {
            foreach ($filters as $filterColumn => $filterValue) {
                if (in_array($filterColumn, $columns)) {
                    // if array → use whereIn
                    if (is_array($filterValue)) {
                        $query->whereIn($filterColumn, $filterValue);
                    }
                    // if single value → use where
                    else {
                        $query->where($filterColumn, $filterValue);
                    }
                }
            }
        }
        $query->where(function ($query) use ($indexes, $comparator, $any, $columns) {
            foreach ($indexes as $column => $value) {
                if (! in_array($column, $columns)) {
                    continue;
                }

                $isPattern = in_array($comparator, [
                    QueryAcceptedComparatorEnum::LIKE,
                    QueryAcceptedComparatorEnum::ILIKE,
                ], true);

                if ($isPattern && ! is_array($value)) {
                    $this->wherePattern($query, $column, '%'.$value.'%', $any);

                    continue;
                }

                if (is_array($value)) {
                    $query->when($any, function ($query) use ($column, $value) {
                        $query->orWhereIn($column, $value);
                    }, function ($query) use ($column, $value) {
                        $query->whereIn($column, $value);
                    });

                    continue;
                }

                $query->when($any, function ($query) use ($column, $comparator, $value) {
                    $query->orWhere($column, $comparator->value, $value);
                }, function ($query) use ($column, $comparator, $value) {
                    $query->where($column, $comparator->value, $value);
                });
            }
        });
        $query->when(isset($indexes['search']), function ($query) use ($indexes, $fields, $columns) {
            if (empty($fields)) {
                $fields = array_filter($columns, function ($column) {
                    return ! in_array($column, ['created_at', 'updated_at']);
                });
            }
            $query->where(function ($query) use ($indexes, $fields, $columns) {
                foreach ($fields as $target) {
                    if (in_array($target, $columns)) {
                        $this->wherePattern($query, $target, '%'.$indexes['search'].'%', true);
                    }
                }
            });
        });
        // Ignore specific records if requested
        $query->when(isset($indexes['ignore']), function ($query) use ($indexes) {
            $query->whereNotIn('id', $indexes['ignore']);
        });

        // Apply special sorting (e.g., random)
        $query->when(! empty($indexes['special_sort']) && $indexes['special_sort'] === 'random', function ($query) {
            $query->inRandomOrder();
        });

        if (isset($relation)) {
            $query->with($relation);
        }

        // Apply regular sorting if no special sorting is requested
        if (! empty($orderBy) && empty($indexes['special_sort'])) {
            foreach ($orderBy as $orderByColumn) {
                $orderByArray = explode(' ', $orderByColumn);
                $orderByColumn = $orderByArray[0];
                $orderByDirection = isset($orderByArray[1]) ? $orderByArray[1] : 'ASC';

                if (in_array($orderByColumn, $columns)) {
                    $query->orderBy($orderByColumn, $orderByDirection);
                }
            }
        } elseif (! empty($defaultOrderBy)) {
            $query->orderBy($defaultOrderBy['name'], $defaultOrderBy['order']);
        }

        if (! empty($indexes['just_query']) && $indexes['just_query'] === true) {
            return $query;
        }

        // Pagination configuration
        if ($limit === -1) {
            $results = $query->get();
        } elseif (! empty($indexes['useCursor'])) {
            $perPage = $limit;
            $results = $query->cursorPaginate($perPage);
        } else {
            $perPage = $limit;
            $currentPage = request()->get('page', 1);
            $results = $query->paginate($perPage, ['*'], 'page', $currentPage, $query->count());
        }

        return $results;
    }

    /**
     * Retrieve all model instances.
     *
     * @return Collection All model instances.
     */
    public function list(): Collection
    {
        return $this->model->all();
    }

    /**
     * Update a model instance.
     *
     * @param  array  $updateData  The attributes to update.
     * @param  mixed  $idOrSlug  The ID or slug of the model to update.
     * @return Model|null The updated model instance, or null if not found.
     *
     * @throws \Exception If unable to update the model.
     */
    public function update(array $updateData, mixed $idOrSlug): ?Model
    {
        return DB::transaction(function () use ($updateData, $idOrSlug) {
            $query = $this->model->newQuery();
            $modelName = get_class($this->model);

            // Find the model by ID or slug
            $model = $query->find($idOrSlug);

            if (! $model && ! is_numeric($idOrSlug) && in_array('slug', $this->getTableColumns())) {
                $model = $query->where('slug', $idOrSlug)->first();
            }

            if (! $model) {
                throw new \Exception(__('exceptions.model_not_found'));
            }

            // Update the model attributes with the provided data
            $model->fill($updateData);

            // Save the updated model
            if ($model->save()) {
                return $model;
            }

            throw new \Exception(__('exceptions.failed_to_update', ['model' => $modelName]));
        });
    }

    /**
     * Delete a model instance by its ID or slug.
     *
     * @param  mixed  $idOrSlug  The ID or slug of the model to delete.
     *
     * @throws \Exception If unable to delete the model.
     */
    public function delete(mixed $idOrSlug): void
    {
        DB::transaction(function () use ($idOrSlug) {
            $query = $this->model->newQuery();
            $model = $query->find($idOrSlug);
            $modelName = get_class($this->model);

            if (! $model && ! is_numeric($idOrSlug) && in_array('slug', $this->getTableColumns())) {
                $model = $query->where('slug', $idOrSlug)->first();
            }

            if (! $model) {
                throw new \Exception(__('exceptions.model_not_found'));
            }

            // Delete the model
            if (! $model->delete()) {
                throw new \Exception(__('exceptions.failed_to_delete', ['model' => $modelName]));
            }
        });
    }

    /**
     * Delete multiple model instances by their IDs.
     *
     * @param  array  $ids  The IDs of the model instances to delete.
     * @return int|null The number of deleted instances.
     *
     * @throws \Exception If unable to delete the instances.
     */
    public function deleteMany(array $ids): ?int
    {
        return DB::transaction(function () use ($ids) {
            $existing = $this->model->newQuery()->whereIn('id', $ids)->count();
            if ($existing === 0) {
                $targetId = implode(', ', $ids);
                throw new \Exception(__('exceptions.model_with_ids_not_found', ['ids' => $targetId]));
            }

            return $this->model->destroy($ids);
        });
    }

    /**
     * Update multiple model instances.
     *
     * @param  array  $ids  An array of IDs or slugs of the models to update.
     * @param  array  $updateData  An array of data to update, where the key is the ID or slug, and the value is the data to update.
     * @return int|null Updated model instances count.
     *
     * @throws \Exception If any update fails.
     */
    public function updateMany(array $ids, array $updateData): ?int
    {
        return DB::transaction(function () use ($ids, $updateData) {
            // check if all IDs exist
            $existingIds = $this->model->whereIn('id', $ids)->pluck('id')->toArray();
            $missingIds = array_diff($ids, $existingIds);

            if (! empty($missingIds)) {
                throw new \Exception(__('exceptions.model_with_ids_not_found', ['ids' => implode(', ', $missingIds)]));
            }

            $updatedCount = 0;

            // Update per chunk (for large data)
            $this->model->whereIn('id', $ids)
                ->chunkById(200, function ($models) use (&$updatedCount, $updateData) {
                    foreach ($models as $model) {
                        $model->fill($updateData);

                        if ($model->save()) {
                            $updatedCount++;
                        } else {
                            throw new \Exception(__('exceptions.failed_to_update_model_with_id', ['model' => get_class($model), 'id' => $model->getKey()]));
                        }
                    }
                });

            return $updatedCount;
        });
    }

    public function firebaseNotification(array $deviceTokens, string $title, string $body, array $data = [], ?string $imageUrl = null): void
    {
        try {
            $messaging = app('firebase.messaging');

            $notification = Notification::create($title, $body, $imageUrl);
            foreach ($deviceTokens as $token) {

                $message = CloudMessage::new()
                    ->withNotification($notification)
                    ->withData($data)
                    ->toToken($token);

                $messaging->send($message);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send Firebase notification: '.$e->getMessage());
        }
    }
}
