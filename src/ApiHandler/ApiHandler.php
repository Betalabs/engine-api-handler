<?php

namespace Betalabs\EngineApiHandler\ApiHandler;

use Illuminate\Support\Facades\Input;
use Marcelgwerder\ApiHandler\Result;
use Marcelgwerder\ApiHandler\ApiHandler as OriginalApiHandler;

class ApiHandler extends OriginalApiHandler
{
    /**
     * Return a new Result object for a single dataset
     *
     * @param  mixed $queryBuilder Some kind of query builder instance
     * @param  array|integer $identification Identification of the dataset to
     *     work with
     * @param  array|boolean $queryParams The parameters used for parsing
     *
     * @return Result                                          Result object
     *     that provides getter methods
     */
    public function parseSingle($queryBuilder, $identification, $queryParams = false)
    {
        if ($queryParams === false) {
            $queryParams = Input::get();
        }

        $parser = new Parser($queryBuilder, $queryParams);
        $parser->parse($identification);

        return new Result($parser);
    }

    /**
     * Return a new Result object for multiple datasets
     *
     * @param  mixed $queryBuilder Some kind of query builder instance
     * @param  array $fullTextSearchColumns Columns to search in fulltext search
     * @param  array|boolean $queryParams A list of query parameter
     *
     * @param bool $relatedFields
     * @return Result
     */
    public function parseMultiple($queryBuilder, $fullTextSearchColumns = array(), $queryParams = false, $relatedFields = false)
    {
        if ($queryParams === false) {
            $queryParams = Input::get();
        }
    
        $queryParams['_config'] = 'meta-total,meta-total-count,meta-filter-count';

        $parser = new Parser($queryBuilder, $queryParams);
        $parser->parse($fullTextSearchColumns, true, $relatedFields);

        return new Result($parser);
    }
}
