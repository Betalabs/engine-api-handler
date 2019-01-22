<?php


namespace Betalabs\EngineApiHandler\ApiHandler;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Input;

abstract class AbstractIndexHandler
{

    /**
     * @var array
     */
    private $queryParams = [];
    /**
     * @var array
     */
    private $sortParam = [];
    /**
     * @var array
     */
    protected $fullTextSearchColumns = [];
    /**
     * @var array
     */
    protected $queryParamExceptions = [];

    /**
     * @const array
     */
    protected const RESERVED_WORDS = [
        '_fields',
        '_sort',
        '_limit',
        '_offset',
        '_config',
        '_with',
        '_q',
        '_filter-approach'
    ];

    /**
     * @const array
     */
    protected const SUPPORTED_OPERATIONS = [
        'st' => '<',
        'gt' => '>',
        'min' => '>=',
        'max' => '<=',
        'lk' => 'LIKE',
        'not-lk' => 'NOT LIKE',
        'in' => 'IN',
        'not-in' => 'NOT IN',
        'not' => '!=',
        'equal' => '='
    ];

    /**
     * @var array
     */
    protected $queryRelatedFields = [];

    /**
     * @var string
     */
    protected $filterApproach;

    /**
     * AbstractIndexHandler constructor.
     */
    public function __construct()
    {
        $this->makeQueryParams();
    }

    /**
     * Make the queryParams.
     */
    private function makeQueryParams(): void
    {
        $query = Input::get();

        if(isset($query["_fields"])) {
            $this->makeQueryParamExceptions($query);
            unset($query["_fields"]);
        }

        if(isset($query['_sort']) && strpos($query['_sort'], '->') !== false) {
            $this->makeSortParam($query['_sort']);
            $query['_sort'] = '-id';
        }

        $this->filterApproach = $query['_filter-approach'] ?? "";
        $this->parseFilters($query);
        $query = array_except($query, $this->queryParamExceptions);

        if (isset($query['_config'])
            && strpos($query['_config'], 'meta-total') !== false
        ) {
            $query['_config'] .= ',meta-total-count';
        }

        $this->queryParams = $query;
    }

    private function makeSortParam($param)
    {
        $this->sortParam['order'] = starts_with($param, '-') ? 'DESC' : 'ASC';
        $this->sortParam['by'] = array_filter(preg_split("/(-|>)/", $param));
    }

    /**
     * Returns a collection of elements
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(): JsonResponse
    {
        $apiHandler = (new ApiHandler())->parseMultiple(
            $this->buildQuery(),
            $this->fullTextSearchColumns,
            $this->queryParams,
            $this->queryRelatedFields ?: false
        );
        $this->sortByRelationField($apiHandler->getBuilder());

        return $this->adaptResponse($apiHandler->getResponse());
    }

    /**
     * Adapt response to be acceptable for Engine
     *
     * @param \Illuminate\Http\JsonResponse $response
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function adaptResponse(JsonResponse $response): JsonResponse
    {
        $data = $response->getData(true);

        if (isset($data['meta']['total_count'])) {
            $data['meta']['meta-total'] = $data['meta']['total_count'];
            unset($data['meta']['total_count']);
        }

        return $response->setData($data);
    }

    /**
     * Build the query to be filtered
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract protected function buildQuery(): Builder;

    /**
     * Parse filters from GET params
     */
    protected function parseFilters(&$query)
    {
        $queryFields = array_diff_key(Input::get(), array_flip(AbstractIndexHandler::RESERVED_WORDS));

        foreach ($queryFields as $key => $field) {
            $count = 0;
            $cleanField = preg_replace(
                "/(-lk|-not-lk|-in|-not-in|-min|-max|-st|-gt|-not)/",
                "",
                $key,
                -1,
                $count
            );

            if (in_array($cleanField, $this->queryParamExceptions)) {
                if($count) {
                    $this->queryParamExceptions[] = $key;
                }
                $this->queryRelatedFields[$key] = $field;
            } else if(method_exists($this, "setJoins")) {
                $query[static::TABLE_NAME . "." . $key] = $query[$key];
                unset($query[$key]);
            }
        }
    }

    /**
     * @param $query
     * @return Builder
     */
    protected function filterQuery($query): Builder
    {
        if ($this->filterApproach == "and") {
            return $this->filterWithAndApproach($query);
        }

        if ($this->filterApproach == "or") {
            return $this->filterWithOrApproach($query);
        }

        return $query;
    }

    /**
     * @param $query
     * @return Builder
     */
    protected function filterWithAndApproach($query): Builder
    {
        foreach ($this->queryRelatedFields as $key => $field) {
            $parsedKey = $this->parseKey($key);
            list($relation, $column) = $parsedKey;
            $operation = $parsedKey[2] ?? "equal";
            $relatedModelsField = str_replace("*", "%", Input::get($key));
            $query->whereHas($relation, function ($query) use ($relatedModelsField, $relation, $column, $operation) {
                $query->where($column, AbstractIndexHandler::SUPPORTED_OPERATIONS[$operation], $relatedModelsField);
            });
        }

        return $query;
    }

    /**
     * @param $query
     * @return Builder
     */
    protected function filterWithOrApproach($query): Builder
    {
        $i = 0;
        foreach ($this->queryRelatedFields as $key => $field) {
            $parsedKey = $this->parseKey($key);
            list($relation, $column) = $parsedKey;
            $operation = $parsedKey[2] ?? "equal";
            $relatedModelsField = str_replace("*", "%", Input::get($key));
            if ($i == 0) {
                $query->whereHas($relation, function ($query) use ($relatedModelsField, $relation, $column, $operation, $i) {
                    $query->where($column, AbstractIndexHandler::SUPPORTED_OPERATIONS[$operation], $relatedModelsField);
                });
            } else {
                $query->orWhereHas($relation, function ($query) use ($relatedModelsField, $relation, $column, $operation, $i) {
                    $query->where($column, AbstractIndexHandler::SUPPORTED_OPERATIONS[$operation], $relatedModelsField);
                });
            }
            $i++;
        }

        return $query;
    }

    /**
     * @param $key
     * @return array[]|false|string[]
     */
    protected function parseKey($key)
    {
        $parsedKey = preg_split("/->|-/", $key);

        return $parsedKey;
    }

    /**
     * @param $query
     */
    private function makeQueryParamExceptions($query): void
    {
        $queryFields = explode(",", $query["_fields"]);
        foreach ($queryFields as $field) {
            if (strpos($field, "->") !== false) {
                $this->queryParamExceptions[] = $field;
            }
        }
    }

    /**
     * Sort by relation field
     *
     * @param $builder
     */
    private function sortByRelationField($builder): void
    {
        if(empty($this->sortParam)) {
            return;
        }
        $parsedSortParamBy = implode(".", $this->sortParam["by"]);
        $builder->getQuery()->orders = null;
        $builder->orderBy($parsedSortParamBy, $this->sortParam["order"]);
    }
}