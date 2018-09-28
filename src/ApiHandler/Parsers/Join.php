<?php

namespace Betalabs\EngineApiHandler\ApiHandler\Parsers;

use App\Http\Requests\ExtraFieldStructure;
use App\Http\Requests\FieldStructure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;

class Join
{

    /** @var string[] */
    private $joinParseTables = [];

    /** @var array */
    private $fieldAliases = [];

    /** @var \Illuminate\Database\Query\Builder */
    private $query;

    /**
     * Add all joins to parse
     *
     * It is necessary for filtering and sorting: the left join adds the table
     * to the query and the where / order clause does not fail.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $fields
     * @param array $filter
     * @param array $sort
     *
     * @return string[]
     */
    public function parseAll(
        Builder $query,
        array $fields = [],
        array $filter = [],
        array $sort = []
    ) {

        $this->query = $query;

        $params = array_merge($fields, $filter, $sort);

        foreach ($params as $key => $value) {
            $this->addSingle($value, $key);
        }

        return $this->joinParseTables;

    }

    /**
     * Add a single join
     *
     * @param $value
     * @param $key
     */
    protected function addSingle($value, $key): void
    {
        if (!($value instanceof FieldStructure) || ($value instanceof ExtraFieldStructure)) {
            return;
        }

        $field = $value->getField();

        if (($dotPosition = strpos($field, '.')) === false) {
            return;
        }

        $table = $value->getRelation()->getRelated()->getTable();

        /*
         * Use to guarantee the table will not be added twice or more
         */
        if (in_array($table, $this->joinParseTables)) {
            return;
        }

        $this->joinParseTables[] = $table;

        /*
         * Add the pivot table to the left join
         */
        if ($value->getRelation() instanceof MorphToMany) {
            $this->addMorphToMany($value->getRelation());
            return;
        }

        $this->addRelation($table, $value);

    }

    /**
     * Add morphToMany left join logic
     *
     * @param \Illuminate\Database\Eloquent\Relations\MorphToMany $relation
     * @param string|null $table
     */
    protected function addMorphToMany(
        MorphToMany $relation,
        string $table = null
    ) {

        $primaryTable = $relation->getTable();
        $primaryTableAlias = str_random(5);

        $this->fieldAliases[$primaryTableAlias] = $primaryTable;

        $this->query->leftJoin(
            $primaryTable . ' AS ' . $primaryTableAlias, function ($join) use ($relation, $primaryTable, $primaryTableAlias) {

            $join->on(
                $relation->getQualifiedParentKeyName(),
                '=',
                str_replace($primaryTable, $primaryTableAlias, $relation->getQualifiedForeignPivotKeyName())
            );

            $join->on(
                $primaryTableAlias . '.' . $relation->getMorphType(),
                '=',
                \DB::raw('"' . str_replace('\\', '\\\\', $relation->getMorphClass()) . '"')
            );

        });

        $secondaryTable = $table ?? $relation->getRelationName();
        $secondaryTableAlias = str_random(5);

        $this->fieldAliases[$secondaryTableAlias] = $secondaryTable;

        $this->query->leftJoin(
            $secondaryTable . ' AS ' . $secondaryTableAlias,
            str_replace($primaryTable, $primaryTableAlias, $relation->getQualifiedRelatedPivotKeyName()),
            '=',
            str_replace($secondaryTable, $secondaryTableAlias, $relation->getRelated()->getQualifiedKeyName())
        );

    }

    /**
     * Add relation left join logic
     *
     * @param $table
     * @param $value
     */
    private function addRelation($table, $value)
    {

        /*
         * In this case the foreign key in the another table
         */
        $thisTableKey = $this->getForeignKey($value);
        $otherTableKey = $value->getRelation()->getQualifiedParentKeyName();

        if ($value->getRelation() instanceof BelongsTo) {
            /*
             * In this case the foreign key is in this table
             */
            $otherTableKey = $table . '.' . $value->getRelation()->getOwnerKey();
        }

        $this->query->leftJoin(
            $table,
            $thisTableKey,
            '=',
            $otherTableKey
        );

    }

    /**
     * Evaluate and return key name
     *
     * @param $value
     *
     * @return mixed
     */
    private function getForeignKey($value)
    {
        if (method_exists($value->getRelation(), 'getQualifiedForeignKeyName')) {
            $thisTableKey = $value->getRelation()->getQualifiedForeignKeyName();
        } else {
            $thisTableKey = $value->getRelation()->getForeignKey();
        }
        return $thisTableKey;
    }

    /**
     * @return array
     */
    public function getFieldAliases(): array
    {
        return $this->fieldAliases;
    }

}