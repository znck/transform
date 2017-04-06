<?php

namespace Znck\Transform;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Transformer
{
    protected $metaKey = '_meta';
    /**
     * @var array
     */
    protected $relations;

    /**
     * @var array
     */
    protected $request;

    /**
     * @var string|null
     */
    protected $root;

    protected $handlers = [];

    /**
     * Transformer constructor.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request->query('_schema', []);

        if (is_string($this->request)) {
            $this->request = json_decode($this->request, true);
        }

        $root = array_first(array_keys($this->request));

        if (count($this->request) === 1 and is_array($this->request[$root])) {
            $this->root = $root;
            $this->request = $this->request[$root];
        }
    }

    public function register(callable $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * Transform anything!
     *
     * @param Eloquent|EloquentCollection|Collection|Paginator $any
     * @param array|null $fields
     *
     * @return array|mixed
     */
    public function transform($any, array $fields = null)
    {
        if ($any instanceof Eloquent) {
            return $this->transformModel($any, $fields);
        } elseif ($any instanceof EloquentCollection) {
            return $this->transformEloquentCollection($any, $fields);
        } elseif ($any instanceof Collection) {
            return $this->transformCollection($any, $fields);
        } elseif ($any instanceof Paginator) {
            return $this->transformPaginator($any, $fields);
        }

        return $any;
    }

    /**
     * Transform an eloquent model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array|null $fields
     *
     * @return array
     */
    public function transformModel(Eloquent $model, array $fields = null): array
    {
        $response = [];

        foreach ($this->normalize($fields) as $key => $value) {
            if (is_array($value)) {
                $response[$key] = $this->transform($model->{$key}, $value);
            } elseif (is_string($value) and is_numeric($key) and !$model->isGuarded($value)) {
                $response[$value] = $model->{$value};
            } elseif (!$model->isGuarded($key)) {
                $response[$key] = $model->{$key};
            }
        }

        foreach ($this->handlers as $handler) {
            $response = array_merge($response, (array)$handler($model));
        }

        return $this->wrap($response, $fields);
    }

    /**
     * Transform an eloquent collection.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     * @param array|null $fields
     *
     * @return array
     */
    public function transformEloquentCollection(EloquentCollection $models, array $fields = null): array
    {
        $response = [];

        foreach ($models as $model) {
            $response[] = $this->transformModel($model, $this->normalize($fields));
        }

        return $this->wrap($response, $fields);
    }

    /**
     * Transform arbitrary collection.
     *
     * @param \Illuminate\Support\Collection $items
     * @param array|null $fields
     *
     * @return array
     */
    public function transformCollection(Collection $items, array $fields = null): array
    {
        $response = [];

        foreach ($items as $item) {
            $response[] = $this->transform($item, $this->normalize($fields));
        }

        return $this->wrap($response, $fields);
    }

    /**
     * Transform any paginator.
     *
     * @param \Illuminate\Contracts\Pagination\Paginator $any
     * @param array|null $fields
     *
     * @return array
     */
    public function transformPaginator(Paginator $any, array $fields = null): array
    {
        $response = $this->transform(collect($any->items()), $this->normalize($fields));
        $response[$this->metaKey] = $this->transformPaginatorMeta($any);

        return $this->wrap($response, $fields);
    }

    /**
     * Transform paginator meta.
     *
     * @param \Illuminate\Contracts\Pagination\Paginator $paginator
     *
     * @return array
     */
    public function transformPaginatorMeta(Paginator $paginator): array
    {
        $paginator = [
            'current_page' => $paginator->currentPage(),
            'count' => count($paginator->items()),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'per_page' => $paginator->perPage(),
        ];

        if ($paginator instanceof LengthAwarePaginator) {
            $paginator ['total_pages'] = $paginator->lastPage();
            $paginator['total'] = $paginator->total();
        }

        return compact('paginator');
    }

    /**
     * Relations to eager load.
     *
     * @return array
     */
    public function relations(): array
    {
        if (!$this->relations) {
            $this->relations = $this->findRelations($this->request);
        }

        return $this->relations;
    }

    /**
     * Find relations recursively.
     *
     * @param array $request
     * @param string $prefix
     *
     * @return array
     */
    protected function findRelations(array $request, string $prefix = ''): array
    {
        $relations = [];
        foreach ($request as $key => $value) {
            if (is_array($value)) {
                $relations[] = $prefix.$key;
                $relations = array_merge($relations, $this->findRelations($value, $key.'.'));
            }
        }

        return $relations;
    }

    /**
     * Wrap response or not.
     *
     * @param array $response
     * @param array|null $fields
     *
     * @return array
     */
    protected function wrap(array $response, array $fields = null): array
    {
        return (is_null($fields) and $this->root) ? [$this->root => $response] : $response;
    }

    /**
     * Choose which fields to use.
     *
     * @param array|null $fields
     *
     * @return array
     */
    protected function normalize(array $fields = null): array
    {
        return is_null($fields) ? $this->request : $fields;
    }
}
