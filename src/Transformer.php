<?php

namespace Znck\Transform;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Transformer
{
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

    /**
     * Transformer constructor.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request->query('_schema', []);

        $root = array_first(array_keys($this->request));

        if (count($this->request) === 1 and is_array($this->request[$root])) {
            $this->root = $root;
            $this->request = $this->request[$root];
        }
    }


    /**
     * Transform anything!
     *
     * @param Eloquent|EloquentCollection|Collection $any
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
        $fields = $this->normalize($fields);

        $response = [];

        foreach ($fields as $key => $value) {
            $response[$key] = is_array($value) ?
                $this->transform($model->${$key}, $value) :
                $model->${$key};
        }

        return $this->wrap($response);
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
        $fields = $this->normalize($fields);

        $response = [];

        foreach ($models as $model) {
            $response[] = $this->transformModel($model, $fields);
        }

        return $this->wrap($response);
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
        $fields = $this->normalize($fields);

        $response = [];

        foreach ($items as $item) {
            $response[] = $this->transform($item, $fields);
        }

        return $this->wrap($response);
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
     * @param $response
     *
     * @return array
     */
    protected function wrap($response): array
    {
        return $this->root ? [$this->root = $response] : $response;
    }

    /**
     * Choose which fields to use.
     *
     * @param array $fields
     *
     * @return array
     */
    protected function normalize(array $fields): array
    {
        return is_null($fields) ? $this->request : $fields;
    }
}
