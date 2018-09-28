<?php

namespace Betalabs\EngineApiHandler\ApiHandler;

use Betalabs\EngineApiHandler\ApiHandler\Parsers\Field;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\Filter;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\Join;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\Sort;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\With;
use Marcelgwerder\ApiHandler\Parser as OriginalParser;

class Parser extends OriginalParser
{

    /**
     * @var string[]
     */
    private $joinParseTables = [];
    /**
     * @var array
     */
    private $fieldAliases = [];
    /**
     * @var Parsers\Join
     */
    private $joinParser;
    /**
     * @var Parsers\With
     */
    private $withParser;

    /**
     * Parser constructor.
     *
     * @param mixed $builder
     * @param array $params
     * @param Parsers\Join|null $joinParser
     * @param Parsers\With|null $withParser
     */
    public function __construct(
        $builder,
        array $params,
        Join $joinParser = null,
        With $withParser = null
    ) {
        parent::__construct($builder, $params);

        $isEloquentRelation = is_subclass_of($builder, '\Illuminate\Database\Eloquent\Relations\Relation');
        if ($isEloquentRelation) {
            $this->builder = $builder;
        }

        $this->joinParser = $joinParser ?? resolve(Join::class);
        $this->withParser = $withParser ?? resolve(With::class);

        $this->functions[] = $params['_filter-approach'] ?? '';
    }

    /**
     * Parse the query parameters with the given options.
     * Either for a single dataset or multiple.
     *
     * This override is necessary to add leftJoin to the query and related
     * wheres work correctly.
     *
     * @param  mixed $options
     * @param  boolean $multiple
     *
     * @param bool $relatedFields
     * @return void
     */
    public function parse($options, $multiple = false, $relatedFields = false)
    {
        $fields = $this->getParam('fields');
        $this->defineWith($fields);

        $this->addAllJoinParse();

        $this->defineFields();

        parent::parse($options, $multiple);

        $this->adaptWhereForJoins($relatedFields);

        if ($this->isEloquentBuilder) {
            //Attach the query builder object back to the eloquent builder object
            $this->builder->setQuery($this->query);
        }
    }

    /**
     * Define "with" params
     *
     * @param $fields
     */
    protected function defineWith($fields)
    {

        if ($with = $this->withParser->define($fields)) {
            $this->params['_with'] = $with;
        }

    }

    /**
     * Add all joins to parse
     *
     * It is necessary for filtering and sorting: the left join adds the table
     * to the query and the where / order clause does not fail.
     *
     */
    protected function addAllJoinParse()
    {

        $this->joinParseTables = $this->joinParser
            ->parseAll(
                $this->query,
                explode(',', $this->getParam('fields')),
                is_array($this->getFilterParams()) ? $this->getFilterParams() : [],
                is_array($this->getParam('sort')) ? $this->getParam('sort') : []
            );

        $this->fieldAliases = $this->joinParser->getFieldAliases();

    }

    /**
     * Define fields
     */
    private function defineFields()
    {

        if (isset($this->params['_fields'])) {

            $this->params['_fields'] = resolve(Field::class)
                ->define(
                    $this->params['_fields'],
                    $this->fieldAliases
                );

        }

    }

    /**
     * Parse the sort param and determine whether the sorting is ascending or
     * descending. A descending sort has a leading "-". Apply it to the query.
     *
     * @param  array|string $sortParam
     *
     * @return void
     */
    protected function parseSort($sortParam)
    {
        /** @var Parsers\Sort $sort */
        $sort = resolve(Sort::class);

        $sort->parse(
            $sortParam,
            $this->query,
            $this->fieldAliases
        );

    }

    /**
     * Parse the remaining filter params
     *
     * This method was override because its default behavior is to "concat"
     * the search parameters using AND, but in some cases in the application
     * OR approach is needed.
     *
     * To change the approach just add "_filter-approach=or" in the GET
     * parameter.
     *
     * @param  array $filterParams
     *
     * @return void
     */
    protected function parseFilter($filterParams)
    {
        /** @var Parsers\Filter $filter */
        $filter = resolve(Filter::class);

        $filter->parseFilter(
            $filterParams,
            $this->query,
            $this->fieldAliases
        );
    }

    /**
     * Check if there exists a method marked with the "@Relation"
     * annotation on the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  string $relationName
     *
     * @return boolean
     */
    protected function isRelation($model, $relationName)
    {
        return method_exists($model, $relationName);
    }

    protected function adaptWhereForJoins($relatedFields)
    {
        if($relatedFields){
            foreach($this->query->wheres as &$where) {
                if($where['type'] == 'Nested'){
                    $where['boolean'] = 'or';
                    unset($where);
                }
            }
        }
    }

}